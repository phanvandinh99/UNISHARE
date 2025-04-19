<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\Like;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $groups = Group::all();

        // Create group posts
        foreach ($groups as $group) {
            $members = $group->members;

            if ($members->isEmpty()) continue;

            $numPosts = rand(3, 7);

            for ($i = 0; $i < $numPosts; $i++) {
                $author = $members->random();

                $post = Post::create([
                    'user_id' => $author->id,
                    'group_id' => $group->id,
                    'title' => $this->getRandomPostTitle(),
                    'content' => $this->getRandomPostContent(),
                    'is_approved' => true,
                    'like_count' => 0,
                    'comment_count' => 0,
                ]);

                // Add comments
                $numComments = rand(0, 5);
                for ($j = 0; $j < $numComments; $j++) {
                    $commenter = $members->random();

                    PostComment::create([
                        'post_id' => $post->id,
                        'user_id' => $commenter->id,
                        'content' => $this->getRandomCommentContent(),
                        'like_count' => 0,
                    ]);

                    $post->increment('comment_count');
                }

                // Add likes
                $numLikes = rand(0, 10);
                $likers = $members->random(min($numLikes, $members->count()));

                foreach (is_iterable($likers) ? $likers : [$likers] as $liker) {
                    Like::create([
                        'user_id' => $liker->id,
                        'likeable_id' => $post->id,
                        'likeable_type' => Post::class,
                    ]);

                    $post->increment('like_count');
                }
            }
        }

        // Create general posts (not in groups)
        $numGeneralPosts = 10;

        for ($i = 0; $i < $numGeneralPosts; $i++) {
            $user = $users->random();

            $post = Post::create([
                'user_id' => $user->id,
                'title' => $this->getRandomPostTitle(),
                'content' => $this->getRandomPostContent(),
                'is_approved' => true,
                'like_count' => 0,
                'comment_count' => 0,
            ]);

            // Add comments
            $numComments = rand(0, 5);
            $commenters = $users->random(min($numComments, $users->count()));

            foreach ($commenters as $commenter) {
                PostComment::create([
                    'post_id' => $post->id,
                    'user_id' => $commenter->id,
                    'content' => $this->getRandomCommentContent(),
                    'like_count' => 0,
                ]);

                $post->increment('comment_count');
            }

            // Add likes
            $numLikes = rand(0, 15);
            $likers = $users->random(min($numLikes, $users->count()));

            foreach ($likers as $liker) {
                Like::create([
                    'user_id' => $liker->id,
                    'likeable_id' => $post->id,
                    'likeable_type' => Post::class,
                ]);

                $post->increment('like_count');
            }
        }
    }

    private function getRandomPostTitle()
    {
        $titles = [
            'Question about the assignment',
            'Study group for the upcoming exam',
            'Looking for project partners',
            'Interesting article I found',
            'Tips for the midterm',
            'Professor announced a deadline extension',
            'New resource for our course',
            'Anyone else struggling with this topic?',
            'Reminder: submission due tomorrow',
            'Helpful tutorial video',
        ];

        return $titles[array_rand($titles)];
    }

    private function getRandomPostContent()
    {
        $contents = [
            'Hey everyone, I\'m having trouble understanding the latest assignment. Can someone explain how to approach problem #3?',
            'I\'m organizing a study group for the upcoming exam. We\'ll meet at the library on Saturday at 2pm. Let me know if you want to join!',
            'Looking for 2-3 people to join my project team. We\'re planning to work on a machine learning application. DM me if interested.',
            'I found this really helpful article that explains the concepts we covered in class today: [link]',
            'For those preparing for the midterm, focus on chapters 3-5 and the practice problems from last week\'s tutorial.',
            'Good news! The professor just announced that the deadline for the project has been extended by one week.',
            'I discovered this great online resource with practice problems and solutions: [link]',
            'Is anyone else finding the latest topic confusing? Maybe we could discuss it here or organize a study session.',
            'Just a reminder that the assignment is due tomorrow at midnight. Don\'t forget to submit!',
            'This video tutorial really helped me understand today\'s lecture: [link]',
        ];

        return $contents[array_rand($contents)];
    }

    private function getRandomCommentContent()
    {
        $comments = [
            'Thanks for sharing this!',
            'I\'m having the same issue. Let me know if you figure it out.',
            'I can help with this. Send me a message and we can discuss further.',
            'Great idea! Count me in.',
            'I found this helpful resource that might answer your question: [link]',
            'I disagree with point #2. Here\'s why...',
            'Has anyone tried the approach mentioned in class?',
            'The professor clarified this in the last lecture. Check the slides.',
            'I\'m available to meet and discuss this tomorrow.',
            'This was really helpful, thank you!',
        ];

        return $comments[array_rand($comments)];
    }
}
