<?php

namespace App\Http\Controllers;

use App\File;
use App\Jisseki;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class FileController extends Controller
{
    //upload
    //エクセルファイルをアップロードしてfilesテーブルに格納する
    //フォーム表示
    public function upload()
    {
        $files = File::latest('updated_at')->take(5)->get();
        return view('file.upload', compact('files'));
    }

    //uploadからのファイル取得
    public function store(Request $request)
    {
        $attrs = $request->validate([
            'file_upload' => 'required|file|max:4096'
        ]);

        //ファイル処理
        if ($request->hasFile('file_upload')) {
            $file = $request->file('file_upload');
            $data = $file->get(); //ファイル読み込み

            $filename = $file->getClientOriginalName();

            //作成
            $item = File::create([
                'filename' => $filename,
                'file' => base64_encode($data)
            ]);

            return redirect(route('file.extract', $item->id));
        }
        return back()->with('msg_error', '追加できませんでした');
    }

    //入力データチェック結果
    const CHK_FULL = 1;     //すべて埋まっていてOK
    const CHK_EMP = 2;      //すべて空白でOK
    const CHK_DATE_NG = 3;  //日付変換NG
    const CHK_HAS_EMP = 4;  //空白が混在NG
    const CHK_CNT_NG = 5; //要素数 NG

    //upload/{id}
    //アップロード済みのエクセルファイルを files より読み出し、
    //実績を解析、jissekisテーブルに格納する
    public function extract($fid)
    {
        $fileitem = File::find($fid);
        if (is_null($fileitem)) {
            //存在しないファイルid
            return redirect(route('file.upload'))->with('msg_error', 'ファイル id ' . $fid . 'は存在しません。');
        }
        $filename = $fileitem->filename;
        if ($fileitem->del_flg > 0) {
            //反映済み
            return redirect(route('file.upload'))->with('msg_error', $fid . ' 「' . $filename . '」このレコードは取込済みです。');
        }

        //実績へ反映処理
        
        //エクセルファイルの内容
        $data = $fileitem->file;

        //テンポラリファイル名
        $fname_tmp = date('YmdHis'); //現在日時からファイル名作成
        for ($i=0;$i<4;$i++) {
            //4文字追加(65:A ～ 90:Z までの範囲)
            $fname_tmp .= chr(mt_rand(65, 90));
        }
        $ext = substr($filename, strrpos($filename, '.')); //.付き拡張子

        $path = "/tmp/".$fname_tmp;
        //base64形式のままテンポラリファイル作成。
        //ストリームのままだとbase64 decodeがエラーとなるため
        file_put_contents($path, $data);

        //ファイル読み出してdecode もう一回テンポラリ保存
        $data_r = base64_decode(file_get_contents($path));
        file_put_contents($path.$ext, $data_r);

        //エクセルファイル読み込み
        $reader = new  XlsxReader();
        $book = $reader->load($path.$ext);
        $sheet = $book->getActiveSheet();

        $str_tmp = ""; //コメント
        //解析
        $f_title = true; //最初の行のタイトルを無視するためのフラグ
        $c_ok = 0; //反映OKの行数
        $c_ng = 0; //反映NGの行数
        foreach ($sheet->getRowIterator() as $row) {
            $r_idx = $row->getRowIndex();
            $raw = $sheet->rangeToArray("A".$r_idx.":H".$r_idx);
            $new_data = $raw[0]; //row側要素1　の　2次元配列　を1次元配列に変換

            if ($f_title) {
                //最初の行はタイトル行なので処理しない
                $f_title = false;
                continue;
            }

            //エラー表示
            $str_tmp .= $r_idx . "行は";

            //入力チェック
            $chk_rslt = self::check_input($new_data);

            //入力数が合わない
            if ($chk_rslt == self::CHK_CNT_NG) {
                $c_ng++;
                $str_tmp .= "データ数の不一致, \n";
                continue;
            } else if ($chk_rslt == self::CHK_HAS_EMP) {
                $c_ng++;
                $str_tmp .= "空白要素あり, \n";
                continue;
            } else if ($chk_rslt == self::CHK_DATE_NG) {
                $c_ng++;
                $str_tmp .= "日付変換NG, \n";
                continue;
            }

            //この先 CHK_FULL か CHK_EMP

            if ($new_data[0] === null) {
                //先頭が空要素は新規追加
                $id = -1;
                $item = null;
            } else {
                $id = intval($new_data[0]);
                $item = Jisseki::find($id);
                $str_tmp .= "id" . $id . " ";
            }

            if ($chk_rslt == self::CHK_FULL) { //フル
                if ($id == -1) {
                    //id が空欄で新規追加
                    $str_tmp .= "新規追加, \n";
                    //id空白でもidを指定する。
                    //$idを指定しないとauto inc機能が働くが、id指定で新規追加した分は考慮されなくて
                    //idが衝突してしまうため
                    $max_id = Jisseki::max('id');
                    $id = $max_id + 1;
                    if (self::access_jisseki('add', $new_data, null, $id)) { $c_ok++; } else { $c_ng++; }
                } else if (is_null($item)) {
                    //レコードが存在しないidで新規追加
                    $str_tmp .= "id指定で新規追加, \n";
                    if (self::access_jisseki('add', $new_data, null, $id)) { $c_ok++; } else { $c_ng++; }
                } else {
                    //id itemともにあり。更新
                    if (!self::check_modified($new_data, $item)) {
                        $str_tmp .= "更新なし, \n";
                    } else {
                        $str_tmp .= "更新, \n";
                        if (self::access_jisseki('modify', $new_data, $item)) { $c_ok++; } else { $c_ng++; }
                    }
                }
            } else if ($chk_rslt == self::CHK_EMP) { //空白
                if ($id == -1) {
                    $str_tmp .= "空行, \n";
                } else {
                    //削除
                    $str_tmp .= "削除, \n";
                    if (self::access_jisseki('del', null, $item)) { $c_ok++; } else { $c_ng++; }
                }
            }
        }

        //テンポラリファイル削除
        unlink($path); // base64ファイル
        unlink($path.$ext); //excelファイル
        // dd($str_tmp);

        //更新フラグ
        if ($c_ng == 0) {
            $fileitem->update(['del_flg' => 1]);
            return redirect(route('file.upload'))->with('msg_success', $fid . ' 「' . $filename . '」実績工数、取り込み完了 ');
        } else {
            return redirect(route('file.upload'))->with('msg_error', $fid . ' 「' . $filename . '」エラー発生あり(' . $c_ng . '個) ' . $str_tmp);
        }

    }

    //------------------
    //解析処理から呼ばれる関数 入力データチェック
    // 戻り値：self::CHK_xxx で始まる定数
    private static function check_input($new_data)
    {
        if (count($new_data) != 8) {
            //8列を想定
            return self::CHK_CNT_NG;
        }

        $c_null = 0;
        // $new_data[0]はid チェックしない
        for ($i=1; $i<8;$i++) {
            if ($new_data[$i] === null) {
                $c_null++;
            }
        }
        if ($c_null == 0) {
            $date = Jisseki::todate($new_data[4]);
            if ($date == false) {
                //日付の変換出来ず
                return self::CHK_DATE_NG;
            }
            //すべてありで問題なし
            return self::CHK_FULL;
        } else if ($c_null == 7) {
            //すべてなしで問題なし
            return self::CHK_EMP;
        } else {
            //中途半端で問題あり
            return self::CHK_HAS_EMP;
        }
    }

    //------------------
    //解析処理から呼ばれる関数 変更点チェック処理
    // 戻り値：違いがあったらtrue
    private static function check_modified($new_data, $item)
    {
        $c_diff = 0;
        if ($item->project != $new_data[1]) { $c_diff++; }
        if ($item->function != $new_data[2]) { $c_diff++; }
        if ($item->output != $new_data[3]) { $c_diff++; }
        if ($item->date != Jisseki::todate($new_data[4])) { $c_diff++; }
        if ($item->hour != floatval($new_data[5])) { $c_diff++; }
        if ($item->user != $new_data[6]) { $c_diff++; }
        if ($item->comments != $new_data[7]) { $c_diff++; }
        // if ($c_diff>0) dd($c_diff, $item, $new_data);
        return ($c_diff != 0);
    }

    //------------------
    //入力データをチェックしてデータベースへ渡すデータ列を作成
    //戻り値：データベースへ渡すデータ配列
    private static function make_attr($new_data = null)
    {
        $attr = [];
        $attr['project'] = $new_data[1];
        $attr['function'] = $new_data[2];
        $attr['output'] = $new_data[3];
        $attr['date'] = Jisseki::todate($new_data[4]);
        $attr['hour'] = floatval($new_data[5]);
        $attr['user'] = $new_data[6];
        $attr['comments'] = $new_data[7];
        return $attr;
    }

    //------------------
    //解析処理から呼ばれる関数 登録処理
    // 戻り値：成功したらtrue
    private static function access_jisseki($kind, $new_data = null, $item = null, $id = -1)
    {
        if ($kind == 'add' || $kind == 'modify') {
            if ($new_data == null) {
                return false;
            }
            $attr = self::make_attr($new_data);
            if ($id > 0) {
                $attr['id'] = $id; // id付き新規作成
            }
            if ($kind == 'add') {
                $ret = Jisseki::create($attr); //$retは作成されたobj
                if ($ret !== null) { return true; } else { return false; }
            } else {
                //変更
                if ($item == null) {
                    return false;
                }
                $ret = $item->update($attr); //$retは個数
                if ($ret > 0) { return true; } else { return false; }
            }
        } else if ($kind == 'del') {
            if ($item == null) {
                return false;
            }
            $ret = $item->delete(); //$retは個数
            if ($ret > 0) { return true; } else { return false; }
        }
        return false;
    }

    //実績をダウンロード
    public function download()
    {
        $items = Jisseki::orderBy('id')->get();
        if (count($items) <= 0) {
            //データなし
            return redirect(route('file.upload'))->with('msg_error', 'データがありません');
        }

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();

        $toexcel = []; //エクセルに出力する全データ
        $row = ['id', 'project', 'function', 'output', 'date', 'hour', 'user', 'comments'];//ヘッダ行
        $toexcel[] = $row;
        foreach ($items as $item) {
            $row = []; //1行分のデータ
            $row[] = $item->id;
            $row[] = $item->project;
            $row[] = $item->function;
            $row[] = $item->output;
            $row[] = Jisseki::format($item->date);
            $row[] = $item->hour;
            $row[] = $item->user;
            $row[] = $item->comments;
            $toexcel[] = $row;
        }
        $sheet->fromArray($toexcel, null, 'A1'); //挿入
        
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(6);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getColumnDimension('H')->setWidth(30);


        $writer = new XlsxWriter($book);
        // return response($writer->save('php://output'), 200)
        // ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        // ->header('Content-Disposition', 'inline; filename="実績一覧"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="実績一覧.xlsx"');
        $writer->save('php://output');
    }
}
