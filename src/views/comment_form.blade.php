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
/* Daftar Komentar */
#comment-list {
    width: auto;
    margin: 20px;
    margin-top: 0;
    font-family: Arial, sans-serif;
    font-size: 14px;
}

#comment-list h3 {
    font-size: 16px;
    margin-bottom: 10px;
    color: #333;
}

.custom-comment-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.custom-comment-list li {
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    background-color: #f9f9f9;
}

.custom-comment-list .comment-author {
    font-weight: bold;
    color: #007bff;
}

.custom-comment-list .comment-text {
    margin-top: 5px;
    color: #555;
}

.custom-comment-list .comment-link {
    font-size: 12px;
    color: #0056b3;
    text-decoration: none;
}

.custom-comment-list .comment-link:hover {
    text-decoration: underline;
}
.pagination {
            font-size:small;
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a {
            margin: 0 5px;
            padding: 8px 12px;
            text-decoration: none;
            color: #06080a;
            border: 1px solid #ccc;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .pagination a:hover {
            background-color: #007bff;
            color: white;
        }

        .pagination .disabled {
            background-color: #b7b7b7;
            color: rgb(96, 96, 96);
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-left:6px;
            margin-right:6px;
            pointer-events: none;
        }

        .pagination .active {
            background-color: #b7b7b7;
            color: rgb(96, 96, 96);
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            pointer-events: none;
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
<div id="comment-list">
    <ul class="custom-comment-list">
        @foreach($comments as $comment)
        <li>
            <div class="comment-author">{{ $comment->name }} @if($comment->user_id)<sup style="color:#fa7a7a">Admin</sup>@endif</div>
            <div class="comment-text">{{ $comment->content }}</div>
        </li>
        @endforeach
    </ul>
@if($comments && $comments->hasMorePages())
    <div class="pagination">
        <!-- Tombol "Previous" -->
        @if ($comments->onFirstPage())
            <div class="disabled">Previous</div>
        @else
            <a href="{{ $comments->previousPageUrl() }}">Previous</a>
        @endif

        <!-- Halaman -->
        @foreach ($comments->getUrlRange(1, $comments->lastPage()) as $page => $url)
            @if ($page == $comments->currentPage())
                <div class="active">{{ $page }}</div>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        <!-- Tombol "Next" -->
        @if ($comments->hasMorePages())
            <a href="{{ $comments->nextPageUrl() }}">Next</a>
        @else
            <div class="disabled">Next</div>
        @endif
    </div>
    @endif
</div>


