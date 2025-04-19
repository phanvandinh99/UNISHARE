<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Danh mục tài liệu
        $documentCategories = [
            [
                'name' => 'Giáo trình',
                'children' => [
                    'Giáo trình chính thức',
                    'Giáo trình tham khảo',
                ]
            ],
            [
                'name' => 'Bài giảng',
                'children' => [
                    'Slide bài giảng',
                    'Ghi chú bài giảng',
                    'Video bài giảng',
                ]
            ],
            [
                'name' => 'Bài tập',
                'children' => [
                    'Bài tập về nhà',
                    'Bài tập thực hành',
                    'Đề thi mẫu',
                ]
            ],
            [
                'name' => 'Tài liệu nghiên cứu',
                'children' => [
                    'Bài báo khoa học',
                    'Luận văn',
                    'Đề tài nghiên cứu',
                ]
            ],
        ];

        // Danh mục bài đăng
        $postCategories = [
            [
                'name' => 'Thông báo',
                'children' => [
                    'Thông báo lớp học',
                    'Thông báo khoa',
                    'Thông báo trường',
                ]
            ],
            [
                'name' => 'Hỏi đáp',
                'children' => [
                    'Câu hỏi học tập',
                    'Câu hỏi kỹ thuật',
                    'Câu hỏi chung',
                ]
            ],
            [
                'name' => 'Chia sẻ',
                'children' => [
                    'Chia sẻ kinh nghiệm',
                    'Chia sẻ tài nguyên',
                    'Chia sẻ cơ hội',
                ]
            ],
        ];

        // Danh mục nhóm
        $groupCategories = [
            [
                'name' => 'Lớp học',
                'children' => [
                    'Lớp chính quy',
                    'Lớp chất lượng cao',
                ]
            ],
            [
                'name' => 'Câu lạc bộ',
                'children' => [
                    'CLB học thuật',
                    'CLB sở thích',
                    'CLB tình nguyện',
                ]
            ],
            [
                'name' => 'Nhóm học tập',
                'children' => [
                    'Nhóm môn học',
                    'Nhóm dự án',
                    'Nhóm nghiên cứu',
                ]
            ],
        ];

        // Tạo danh mục tài liệu
        $this->createCategories($documentCategories, 'document');

        // Tạo danh mục bài đăng
        $this->createCategories($postCategories, 'post');

        // Tạo danh mục nhóm
        $this->createCategories($groupCategories, 'group');
    }

    /**
     * Tạo danh mục và danh mục con
     */
    private function createCategories($categories, $type, $parentId = null)
    {
        foreach ($categories as $category) {
            $name = is_array($category) ? $category['name'] : $category;

            $newCategory = Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'type' => $type,
                'parent_id' => $parentId,
                'description' => "Danh mục {$name}",
                'icon' => null,
                'color' => $this->getRandomColor(),
            ]);

            if (is_array($category) && isset($category['children'])) {
                $this->createCategories($category['children'], $type, $newCategory->id);
            }
        }
    }

    /**
     * Tạo màu ngẫu nhiên
     */
    private function getRandomColor()
    {
        $colors = [
            '#4299e1', // blue-500
            '#48bb78', // green-500
            '#ed8936', // orange-500
            '#9f7aea', // purple-500
            '#f56565', // red-500
            '#38b2ac', // teal-500
            '#667eea', // indigo-500
            '#d53f8c', // pink-500
        ];

        return $colors[array_rand($colors)];
    }
}
