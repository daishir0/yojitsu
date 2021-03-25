@if (session()->has('msg_error'))
{{-- 独自のエラーメッセージ(withで付ける) --}}
<div class="error-message">
    {{session('msg_error')}}
</div>
@endif

@if (session()->has('msg_success'))
{{-- 成功メッセージも表示(withでredirect()やback()につける。) --}}
<div class="success-message">
    {{session('msg_success')}}
</div>
@endif