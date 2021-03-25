<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>アップロード</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

        <!-- Styles -->
        <link rel="stylesheet" href="{{asset('css/app.css')}}">
        <link rel="stylesheet" href="{{asset('css/style.css')}}">
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            <div class="content">
                <div class="title m-b-md">
                    Laravel
                </div>
                @include('include.message');
                <div class="drop-zone" id="js-dropzone">
                    <form action="{{route('file.store')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        <label for="file_upload" class="select-file" id="js-select-file">
                            <p>
                                <span>エクセルファイルをドラッグ & ドロップする</span><br>
                                <span>または、</span><br><a href="#" class="btn-send_sub">ファイルを選択する</a>
                            </p>
                            <input type="file" name="file_upload" id="file_upload" accept=".xls,.xlsx,xlsm">
                        </label>
                        <div class="drop-zone-foot">
                            <div class="bar-gray" style="display:none;">
                                <p></p>
                                <a class="btn-cancel" href="#"><span>キャンセル</span></a>
                            </div>
                            <button id="to-Loading" class="btn-send is-disabled">送信する</button>
                        </div>
                    </form>
                </div>
                <div class="list">
                    <p>直近5件</p>
                    <ul>
                    @foreach ($files as $item)
                        <li>{{"[".$item->id."]".$item->filename."[".$item->updated_at.(($item->del_flg==0)?"送信(未処理)":"処理済み")."]"}}</li>
                    @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <script src="{{asset('js/app.js')}}"></script>
        <script src="{{asset('js/upload.js')}}"></script>
    </body>
</html>
