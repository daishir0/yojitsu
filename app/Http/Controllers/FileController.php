<?php

namespace App\Http\Controllers;

use App\File;
use Illuminate\Http\Request;

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

            //pyramid2の作成
            $filename = $file->getClientOriginalName();

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
    public function extract($id)
    {
        $item = File::find($id);
        if (is_null($item)) {
            //存在しないファイルid
            return redirect(route('file.upload'))->with('msg_error', 'ファイル id ' . $id . 'は存在しません。');
        }
        $filename = $item->filename;
        if ($item->del_flg > 0) {
            //反映済み
            return redirect(route('file.upload'))->with('msg_error', $id . ' 「' . $filename . '」はすでに反映されています。');
        }
        //実績へ反映処理
        
        //エクセルファイルの内容
        $data = $item->file;

        //テンポラリファイル名
        $fname_tmp = date('YmdHis'); //現在日時からファイル名作成
        for ($i=0;$i<4;$i++) {
            //4文字追加(65:A ～ 90:Z までの範囲)
            $fname_tmp .= chr(mt_rand(65, 90));
        }
        $ext = substr($filename, strrpos($filename, '.')); //.付き拡張子

        $path = "/tmp/".$fname_tmp;
        //テンポラリファイル作成 base64のまま。ストリームのままだとdecodeエラー発生するため
        file_put_contents($path, $data);

        //ファイル読み出してdecode もう一回テンポラリ保存
        $data_r = base64_decode(file_get_contents($path));
        file_put_contents($path.$ext, $data_r);

        // テストダウンロード処理
        // return response($data_r, 200)
        // ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        // ->header('Content-Disposition', 'inline; filename="'.$item->filename.'"');




        //テンポラリファイル削除
        unlink($path);
        // unlink($path.$ext);


        return redirect(route('file.upload'))->with('msg_success', $id . ' 「' . $filename . '」を実績に反映しました。');
    }

    //実績をダウンロード
    public function download()
    {

    }
}
