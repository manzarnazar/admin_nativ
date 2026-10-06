<?php

namespace App\Services\Api;

use App\Enums\FaqTopicType;
use App\Models\Faq;
use App\Models\HowItWorksStep;
use Illuminate\Pagination\LengthAwarePaginator;

class HelpSupportService
{
    /**
     * Get Help & Support page data with How It Works and Topics & FAQs.
     *
     * @param  int|null  $limit  Number of FAQs to return
     * @param  int|null  $offset  Number of FAQs to skip
     * @param  string|null  $search  Search term to filter FAQs
     * @param  string  $type  `help_support` (default) or `partner`
     * @return array<string, mixed>
     */
    public function getHelpSupportData(?int $limit = null, ?int $offset = null, ?string $search = null, string $type = 'help_support'): array
    {
        $topicType = $type === 'partner' ? FaqTopicType::BecomePartner : FaqTopicType::HelpSupport;

        $query = Faq::query()
            ->whereHas('topic', fn ($q) => $q->where('type', $topicType))
            ->with('topic:id,title,slug,description,sort_order');

        if ($search) {
            $searchTerms = explode(' ', trim($search));
            $query->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    if (! empty($term)) {
                        $q->where(function ($subQ) use ($term) {
                            $subQ->where('question', 'like', "%{$term}%")
                                ->orWhere('answer', 'like', "%{$term}%")
                                ->orWhereHas('topic', function ($topicQ) use ($term) {
                                    $topicQ->where('title', 'like', "%{$term}%")
                                        ->orWhere('description', 'like', "%{$term}%");
                                });
                        });
                    }
                }
            });
        }

        $total = $query->count();

        if ($offset !== null) {
            $query->skip($offset);
        }

        if ($limit !== null) {
            $query->take($limit);
        }

        $items = $query
            ->orderBy('sort_order')
            ->orderBy('faq_topic_id')
            ->get();

        $topicsAndFaqs = $this->groupFaqsByTopic($items->all());

        // When search is applied, filter out topics with no matching FAQs
        if ($search) {
            $topicsAndFaqs = array_filter($topicsAndFaqs, fn ($topic) => ! empty($topic['faqs']));
            $topicsAndFaqs = array_values($topicsAndFaqs);
        }

        $responseData = [
            // No partner-specific steps exist — how_it_works is only meaningful for the
            // default help_support type, so it's returned empty for type=partner.
            'how_it_works' => $topicType === FaqTopicType::HelpSupport ? $this->getHowItWorksSteps() : [],
            'topics_and_faqs' => $topicsAndFaqs,
        ];

        // If limit was provided, include pagination data
        if ($limit !== null) {
            $responseData['paginator'] = new LengthAwarePaginator(
                $items,
                $total,
                $limit,
                ($offset !== null && $limit > 0) ? (int) floor($offset / $limit) + 1 : 1
            );
        }

        return $responseData;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getHowItWorksSteps(): array
    {
        return HowItWorksStep::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (HowItWorksStep $step) => [
                'id' => $step->id,
                'title' => $step->title,
                'description' => $step->description,
                'sort_order' => $step->sort_order,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Group FAQs by topic for response
     *
     * @param  array<int, Faq>  $faqs
     * @return array<int, array<string, mixed>>
     */
    private function groupFaqsByTopic(array $faqs): array
    {
        $grouped = collect($faqs)
            ->groupBy('faq_topic_id')
            ->map(fn ($faqs, $topicId) => [
                'id' => $topicId,
                'title' => $faqs->first()->topic->title ?? null,
                'slug' => $faqs->first()->topic->slug ?? null,
                'description' => $faqs->first()->topic->description ?? null,
                'sort_order' => $faqs->first()->topic->sort_order ?? null,
                'faqs' => $faqs->map(fn ($faq) => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'sort_order' => $faq->sort_order,
                ])->values()->toArray(),
            ])
            ->sortBy('sort_order')
            ->values()
            ->toArray();

        return $grouped;
    }
}
