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
            $strraw = $sheet->rangeToArray("A".$r_idx.":H".$r_idx);
            $str = $strraw[0]; //row側要素1　の　2次元配列　を1次元配列に変換

            //チェック
            if (count($str)==8) { //8列を想定 つまりコメントがないとNG
                if ($f_title) {
                    //最初の行はタイトル行なので処理しない
                    $f_title = false;
                    continue;
                }

                if ($str[0] === null) {
                    //先頭が空要素は新規追加
                    $id = -1;
                    $item = null;
                } else {
                    $id = intval($str[0]);
                    $item = Jisseki::find($id);
                }
                $str_tmp .= $r_idx . "行 " . $id . "は";

                //処理判断
                if ($id > 0 && is_null($item)) {
                    //存在しないため無視
                    $str_tmp .= "存在しません。無視します。\n";
                    $c_ng++;
                } else {
                    //行ごとに 追加・削除・更新 の判断
                    $c_null = 0;
                    for ($i=1; $i<8;$i++) {
                        if ($str[$i] === null) {
                            $c_null++;
                        }
                    }
                    // 1 ～ 7 が全部なければ削除
                    //　　　　　全部あれば 追加、あるいは、更新
                    //　　　　　中途半端なら何もしない
                    if ($c_null == 0) {
                        if ($id == -1) {
                            //新規追加
                            $str_tmp .= "新規追加です\n";
                            if (self::access_jisseki('add', $str)) { $c_ok++; } else { $c_ng++; }
                        } else {
                            //更新
                            $str_tmp .= "更新です\n";
                            if (self::access_jisseki('modify', $str, $item)) { $c_ok++; } else { $c_ng++; }
                        }
                    } else if ($c_null == 7) {
                        if ($id == -1) {
                            $str_tmp .= "空行です\n";
                        } else {
                            //削除
                            $str_tmp .= "削除です\n";
                            if (self::access_jisseki('del', null, $item)) { $c_ok++; } else { $c_ng++; }
                        }
                    } else {
                        //何もしない
                        $str_tmp .= $c_null . "個空白のため無視します\n";
                        $c_ng++;
                    }
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
            return redirect(route('file.upload'))->with('msg_success', $fid . ' 「' . $filename . '」実績工数、取り込み完了');
        } else {
            return redirect(route('file.upload'))->with('msg_error', $fid . ' 「' . $filename . '」エラー発生あり(' . $c_ng . '個)' . $str_tmp);
        }

    }

    //------------------
    //解析処理から呼ばれる関数 登録処理
    // 戻り値：成功したらtrue
    private static function access_jisseki($kind, $str = null, $item = null)
    {
        if ($kind == 'add' || $kind == 'modify') {
            if ($str == null) {
                return false;
            }
            $attr = [];
            $attr['project'] = $str[1];
            $attr['function'] = $str[2];
            $attr['output'] = $str[3];
            $attr['date'] = $str[4]; // バリデート必要
            $attr['hour'] = floatval($str[5]);
            $attr['user'] = $str[6];
            $attr['comments'] = $str[7];
            if ($kind == 'add') {
                $ret = Jisseki::create($attr); //$retは作成されたobj
                if ($ret !== null) { return true;} else { return false; }
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
            $row[] = $item->date;
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
