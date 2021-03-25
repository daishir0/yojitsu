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
                'file' => $data
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
        //反映処理


        return redirect(route('file.upload'))->with('msg_success', $id . ' 「' . $filename . '」を実績に反映しました。');
    }

    //実績をダウンロード
    public function download()
    {

    }
}
