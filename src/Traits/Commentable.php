<?php
namespace Leazycms\FLC\Traits;

use Leazycms\FLC\Models\Comment;

trait Commentable
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable')->whereNull('parent_id');
    }

    public function addComment(array $source)
    {

    }

}
