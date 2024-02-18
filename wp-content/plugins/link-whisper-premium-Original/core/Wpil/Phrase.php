<?php

/**
 * Phrase controller
 */
class Wpil_Phrase
{
    /**
     * Check if post title has common words with keyword and increase post score
     *
     * @param $phrases
     * @param $keyword
     */
    public static function TitleKeywordsCheck(&$phrases, $keyword)
    {
        foreach ($phrases as $phrase) {
            $keyword_words = Wpil_Word::getStemmedWords($keyword);
            $suggestion_words = Wpil_Word::getStemmedWords($phrase->suggestions[0]->post->title);
            $common_words = 0;
            foreach ($suggestion_words as $word) {
                if (in_array($word, $keyword_words)) {
                    $common_words++;
                }
            }

            $phrase->suggestions[0]->post_score += $common_words * 100;
        }
    }

    /**
     * Sort inbound post suggestions by post score DESC
     *
     * @param $phrases
     */
    public static function InboundSort(&$phrases)
    {
        usort($phrases, function($a, $b){
            if ($a->suggestions[0]->post_score == $b->suggestions[0]->post_score) {
                return 0;
            }
            return ($a->suggestions[0]->post_score > $b->suggestions[0]->post_score) ? -1 : 1;
        });
    }

    /**
     * Delete phrases with sugeestion point < 3
     *
     * @param $phrases
     * @return array
     */
    public static function deleteWeakPhrases(&$phrases)
    {
        if (count($phrases) <= 10) {
            return $phrases;
        }

        $three_and_more = 0;
        foreach ($phrases as $phrase) {
            if ($phrase->suggestions[0]->post_score >=3) {
                $three_and_more++;
            }
        }

        foreach ($phrases as $key => $phrase) {
            if ($phrase->suggestions[0]->post_score < 3) {
                if ($three_and_more < 10) {
                    $three_and_more++;
                } else {
                    unset($phrases[$key]);
                }
            }
        }

        return $phrases;
    }
}