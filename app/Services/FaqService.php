<?php

namespace App\Services;

use App\Models\Faq;

class FaqService
{
    public function createFaq(int $topicId, array $data): Faq
    {
        $data['faq_topic_id'] = $topicId;
        $data['sort_order'] = Faq::query()->where('faq_topic_id', $topicId)->max('sort_order') + 1;

        return Faq::query()->create($data);
    }

    public function updateFaq(Faq $faq, array $data): Faq
    {
        $faq->update($data);

        return $faq;
    }

    public function deleteFaq(Faq $faq): void
    {
        $topicId = $faq->faq_topic_id;

        $faq->delete();

        $this->reSequence($topicId);
    }

    private function reSequence(int $topicId): void
    {
        Faq::query()
            ->where('faq_topic_id', $topicId)
            ->orderBy('sort_order')
            ->get()
            ->each(function (Faq $faq, int $index): void {
                $faq->update(['sort_order' => $index + 1]);
            });
    }
}
