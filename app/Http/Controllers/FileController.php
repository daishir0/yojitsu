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
        return view('file.upload');
    }

    //uploadからのファイル取得
    public function store(Request $request)
    {
        $id = 1; //追加したレコードのid
        return redirect(route('file.extract', $id));
    }

    //upload/{id}
    //アップロード済みのエクセルファイルを files より読み出し、
    //実績を解析、jissekisテーブルに格納する
    public function extract($id)
    {
        $filename = "test.xlsx";
        return redirect(route('file.upload'))->with('msg_success', $id . ' 「' . $filename . '」を実績に反映しました。');
    }

    //実績をダウンロード
    public function download()
    {

    }
}
