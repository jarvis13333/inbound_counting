(function () {
  document.querySelectorAll('[data-photo-upload]').forEach(function (block) {
    var input = block.querySelector('input[type="file"]');
    var preview = block.querySelector('[data-photo-preview]');
    var btn = block.querySelector('.take-photo-btn');
    if (!input || !preview) return;

    if (btn) {
      btn.addEventListener('click', function () {
        input.click();
      });
    }

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) return;
      if (!file.type.startsWith('image/')) {
        alert('Please select an image file.');
        input.value = '';
        return;
      }
      var reader = new FileReader();
      reader.onload = function (e) {
        preview.innerHTML = '<img src="' + e.target.result + '" alt="Photo preview">';
      };
      reader.readAsDataURL(file);
    });
  });
})();
