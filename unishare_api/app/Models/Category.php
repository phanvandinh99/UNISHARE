<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'parent_id',
        'description',
        'icon',
        'color',
    ];

    /**
     * Lấy danh mục cha
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Lấy danh sách danh mục con
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->with('children');
    }

    /**
     * Lấy danh sách tài liệu thuộc danh mục
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Lấy danh sách bài đăng thuộc danh mục
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Lấy danh sách nhóm thuộc danh mục
     */
    public function groups()
    {
        return $this->hasMany(Group::class);
    }
}
