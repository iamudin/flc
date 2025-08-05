<li style="padding-bottom:2px !important;margin-bottom:10px !important;padding-top:5px !important">
    <div class="comment-author" style="display: block !important">

       @if($comment->parent_id)
       &#x2936;

       @endif
        @if($comment->user_id)
           <span style="color:#636363 !important"> {{ $comment->user->name }}
            <sup style="color:#fa7a7a !important">{{ strtoupper($comment->user->level) }}</sup></span>
        @else
        <span style="color:#636363 !important"> {{ $comment->name }}</span>
        @endif
    </div>

    <div style="color:#979797 !important;font-size:9px !important;line-height: normal !important;">
        Pada {{ $comment->created_at->format('d F Y H:i T') }}
    </div>

    <div class="comment-text" style="padding-bottom: 3px">
        {{ $comment->comment_content }}
    </div>

    @if($comment->childs->count())
        <ul style="list-style:none !important;margin:10px 0 0 0 !important;padding:0 !important;">
            @foreach($comment->childs as $child)
                @include('flc::comment_list', ['comment' => $child])
            @endforeach
        </ul>
    @endif
</li>
