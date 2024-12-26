<style>
#tiny-comment-form {
    width: auto;
    margin: 20px;
    font-family: Arial, sans-serif;
    font-size: 14px;
}

#tiny-comment-form form {
    display: flex;
    flex-direction: column;
}

#tiny-comment-form input,
#tiny-comment-form textarea {
    margin-bottom: 10px;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
}

#tiny-comment-form textarea {
    resize: none;
}

#submit-button {
    position: relative;
    padding: 10px 20px;
    font-size: 14px;
    border: none;
    background-color: #007bff;
    color: white;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    overflow: hidden;
}

#submit-button:hover {
    background-color: #0056b3;
}

/* Loading Spinner */
#loading-spinner {
    width: 18px;
    height: 18px;
    border: 3px solid #fff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

@keyframes spin {
    from {
        transform: translate(-50%, -50%) rotate(0deg);
    }
    to {
        transform: translate(-50%, -50%) rotate(360deg);
    }
}

/* Sembunyikan teks saat loading */
#submit-button.loading #button-text {
    visibility: hidden;
}

#response-message {
    margin-top: 10px;
    font-size: 14px;
    text-align: center;
}
#response-message.success {
    color: green;
    background:#c7f0da

}
#response-message.error {
    color: red;
    background:#e8bebe
}

</style>
<div id="tiny-comment-form">
    <div id="response-message" style="display: none;padding:20px;text-align:center;margin-bottom:20px"></div>
    <div class="box-comment">
    <form id="comment-form" method="post">
        @csrf
        <input type="text" name="name" placeholder="Name" required >
        @if(isset($attribute['email']) && $attribute['email']!==false)
        <input type="email" name="email" placeholder="Email" >
        @endif
        @if(isset($attribute['link'])&& $attribute['link']!==false)
        <input type="url" name="link" placeholder="Profile Link">
        @endif
        @if(isset($attribute['comment_meta']) && is_array($attribute['comment_meta']))
        @foreach($attribute['comment_meta'] as $meta)
        <input required type="text" name="comment_meta[{{$meta}}]" placeholder="{{str($meta)->headline()}}">
        @endforeach
        @endif
        @if(isset($attribute['content'])&& $attribute['content']!==false)

        <textarea name="content" rows="4" placeholder="Tulis Sesuatu..." ></textarea>
        @endif
        <div style="text-align: right">
            <button type="submit" id="submit-button">
                <span id="button-text">Kirim</span>
                <span id="loading-spinner" style="display: none;"></span>
            </button>
        </div>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
          $('#comment-form').on('submit', function (e) {
              e.preventDefault();
              var formData = $(this).serialize();
              var $button = $('#submit-button');
            $button.addClass('loading');
            $('#loading-spinner').show();
              $.ajax({
                  url: '{{request()->fullUrl()}}',
                  method: 'POST',
                  data: formData,
                  success: function (response) {
                      $('#response-message')
                          .removeClass('error')
                          .addClass('success')
                          .text('Terima Kasih atas partisipasi anda')
                          .fadeIn();
                          $('#comment-form')[0].reset();
                          $('.box-comment').remove()
                  },
                  error: function () {

                      $('#response-message')
                          .removeClass('success')
                          .addClass('error')
                          .text('Ups, Gagal Mengirim Silahkan coba beberapa saat lagi.')
                          .fadeIn();
                          $('#comment-form')[0].reset();
                          $('.box-comment').remove()
                  }
              });
          });
      });
  </script>
</div>
</div>


