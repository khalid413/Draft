<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $guarded = [];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /** Public playlist entry; the stored path never leaves the server. */
    public function track(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'src' => '/music/'.$this->id.'/audio'];
    }
}
