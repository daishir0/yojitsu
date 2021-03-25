// ファイルのドラッグアンドドロップ
$(function () {
  // drop_area以外にドロップされた場合、ファイルを開かない
  $(document).on('dragenter', function (event) {
    event.stopPropagation();
    event.preventDefault();
  });
  $(document).on('dragover', function (event) {
    event.stopPropagation();
    event.preventDefault();
  });
  $(document).on('drop', function (event) {
    event.stopPropagation();
    event.preventDefault();
  });

  //ファイルドラッグ＆ドロップ
  $('#js-select-file').on('drop', function (event) {
    var files = event.originalEvent.dataTransfer.files;
    if (files.length > 1) {
      alert('複数ファイルは選択できません。');
      return false;
    }
    var name = files[0].name;
    if (!name.match(/\.(xls[xm]?)$/i)) {
      alert('エクセルファイルをアップロードしてください。');
      return false;
    }
    $('#file_upload')[0].files = files;
    $('.bar-gray').show();
    $('.bar-gray p').text(name);
    $('#to-Loading').removeClass('is-disabled');
  });

  // ファイル選択ボタン
  $('#file_upload').on('change', function() {
    var files = $('#file_upload')[0].files;
    var name = files[0].name;
    if (!name.match(/\.(xls[xm]?)$/i)) {
      alert('エクセルファイルをアップロードしてください。');
      return false;
    }
    $('.bar-gray').show();
    $('.bar-gray p').text(name);
    $('#to-Loading').removeClass('is-disabled');
    return false;
  });

  //ファイルキャンセル
  $('.drop-zone-foot .btn-cancel').on('click', function () {
    $('.bar-gray').hide();
    $('.bar-gray p').text('');
    $('#to-Loading').addClass('is-disabled');
    $('#file_upload').val(''); //選択解除
    return false
  });
});
