<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class View extends Model {
    protected $fillable = [
        'chapter_id', 'ip'
    ];

    public function chapter() {
        return $this->belongsTo(Chapter::class);
    }

    public static function incrementIfNew($chapter, $ip) {
        $chapter->views_list()->where('ip', $ip)->firstOr(function () use ($chapter, $ip) {
            View::create(['chapter_id' => $chapter->id, 'ip' => $ip]);
            $chapter->views++;
            $chapter->timestamps = false;
            $chapter->save();
            $chapter->timestamps = true;
        });
    }

}
