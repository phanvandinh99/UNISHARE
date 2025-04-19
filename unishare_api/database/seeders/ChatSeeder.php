<?php

namespace Database\Seeders;

use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        
        // Create private chats (1:1)
        $numPrivateChats = 10;
        
        for ($i = 0; $i < $numPrivateChats; $i++) {
            $participants = $users->random(2);
            
            $chat = Chat::create([
                'name' => null, // Private chats don't need names
                'type' => 'private',
            ]);
            
            // Add participants
            foreach ($participants as $participant) {
                ChatParticipant::create([
                    'chat_id' => $chat->id,
                    'user_id' => $participant->id,
                ]);
            }
            
            // Add messages
            $numMessages = rand(3, 15);
            
            for ($j = 0; $j < $numMessages; $j++) {
                $sender = $participants[rand(0, 1)];
                
                Message::create([
                    'chat_id' => $chat->id,
                    'user_id' => $sender->id,
                    'content' => $this->getRandomMessageContent(),
                    'is_read' => rand(0, 1),
                    'read_at' => rand(0, 1) ? now() : null,
                ]);
            }
        }
        
        // Create group chats
        $numGroupChats = 5;
        
        for ($i = 0; $i < $numGroupChats; $i++) {
            $numParticipants = rand(3, 8);
            $participants = $users->random($numParticipants);
            
            $chat = Chat::create([
                'name' => $this->getRandomGroupChatName(),
                'type' => 'group',
            ]);
            
            // Add participants
            foreach ($participants as $participant) {
                ChatParticipant::create([
                    'chat_id' => $chat->id,
                    'user_id' => $participant->id,
                ]);
            }
            
            // Add messages
            $numMessages = rand(5, 25);
            
            for ($j = 0; $j < $numMessages; $j++) {
                $sender = $participants->random();
                
                Message::create([
                    'chat_id' => $chat->id,
                    'user_id' => $sender->id,
                    'content' => $this->getRandomMessageContent(),
                    'is_read' => rand(0, 1),
                    'read_at' => rand(0, 1) ? now() : null,
                ]);
            }
        }
    }
    
    private function getRandomGroupChatName()
    {
        $names = [
            'Study Group',
            'Project Team',
            'Assignment Help',
            'Exam Prep',
            'Course Discussion',
            'Research Group',
            'Lab Partners',
            'Homework Group',
            'Class of 2023',
            'CS Department',
        ];
        
        return $names[array_rand($names)] . ' ' . rand(1, 10);
    }
    
    private function getRandomMessageContent()
    {
        $messages = [
            'Hey, how\'s it going?',
            'Did you finish the assignment yet?',
            'Can someone explain the last lecture?',
            'I\'m stuck on problem 3, any hints?',
            'When is the next meeting?',
            'Has anyone started on the project?',
            'The deadline was extended to next Friday.',
            'I found a great resource for this topic: [link]',
            'Who\'s going to the study session tomorrow?',
            'Can we reschedule our meeting?',
            'Did anyone take notes in class today?',
            'I\'ll be late for the group meeting, start without me.',
            'Has the professor posted the grades yet?',
            'This assignment is much harder than I expected.',
            'Thanks for your help!',
            'I\'m confused about the requirements for the project.',
            'Good luck on the exam everyone!',
            'Does anyone have the textbook PDF?',
            'I\'m available to meet anytime after 3pm.',
            'Let me know if you need any help with the code.',
        ];
        
        return $messages[array_rand($messages)];
    }
}
