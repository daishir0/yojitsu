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
    $('#form1').submit();
  });
});
