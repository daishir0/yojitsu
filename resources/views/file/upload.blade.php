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
                @include('include.message')
                <div class="drop-zone" id="js-dropzone">
                    <form id="form1" action="{{route('file.store')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        <label class="select-file" id="js-select-file">
                            <p>
                                <span>エクセルファイルをドラッグ & ドロップする</span><br>
                            </p>
                        </label>
                        <input type="file" name="file_upload" id="file_upload" accept=".xls,.xlsx,xlsm" style="display:none;">
                        <div class="drop-zone-foot">
                            <button id="to-Loading" class="btn-send is-disabled" style="display:none;">送信する</button>
                        </div>
                    </form>
                </div>
                <div class="list">
                    <p>直近5件</p>
                    <ul>
                    @foreach ($files as $item)
                        <li>{{"[".$item->id."]".$item->filename."[".$item->updated_at.(($item->del_flg==0)?"送信(未完了)":"処理済み")."]"}}</li>
                    @endforeach
                    </ul>
                </div>
                <div class="download">
                    <a class="btn-download" href="{{route('file.download')}}">実績ファイルダウンロード</a>
                </div>
            </div>
        </div>
        <script src="{{asset('js/app.js')}}"></script>
        <script src="{{asset('js/upload.js')}}??20210330"></script>
    </body>
</html>
