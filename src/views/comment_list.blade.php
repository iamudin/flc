<li style="padding-bottom:2px;margin-bottom:10px;padding-top:5px">
    <div class="comment-author" style="display: block">

       @if($comment->parent_id)
       &#x2936;

       @endif
        @if($comment->user_id)
           <span style="color:#636363"> {{ $comment->user->name }}
            <sup style="color:#fa7a7a">{{ strtoupper($comment->user->level) }}</sup></span>
        @else
        <span style="color:#636363"> {{ $comment->name }}</span>
        @endif
    </div>

    <div style="color:#979797;font-size:9px;">
        Pada {{ $comment->created_at->format('d F Y H:i T') }}
    </div>

    <div class="comment-text" style="padding-bottom: 3px">
        {{ $comment->content }}
    </div>

    @if($comment->child->count())
        <ul style="list-style:none;margin-top:10px;padding:0;margin-bottom:0">
            @foreach($comment->child as $child)
                @include('flc::comment_list', ['comment' => $child])
            @endforeach
        </ul>
    @endif
</li>
