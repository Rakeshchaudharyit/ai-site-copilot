<?php
namespace AISC\AI;

if (!defined('ABSPATH')) exit;

class PromptManager {

    public function seo_fix_prompt(array $post): string {
        $title   = (string) ($post['title'] ?? '');
        $content = (string) ($post['content'] ?? '');
        $excerpt = (string) ($post['excerpt'] ?? '');

        $content = wp_strip_all_tags($content);
        $excerpt = wp_strip_all_tags($excerpt);

        // Keep prompt compact but strong (best for quality + cost)
        return
"You're an SEO assistant for a WordPress website.
Create an improved SEO title, meta description, and ONE focus keyphrase.

Rules:
- SEO title: 50-60 chars, punchy, no clickbait, keep meaning.
- Meta description: 140-160 chars, clear value, natural language.
- Focus keyphrase: 2-5 words, specific, based on content, no brand name stuffing.
- Do NOT use quotes.
- Do NOT use emojis.
- Return STRICT JSON only (no extra text).

Input:
Title: {$title}
Excerpt: {$excerpt}
Content: " . mb_substr($content, 0, 1800) . "

Output JSON schema:
{
  \"seo_title\": \"...\",
  \"meta_description\": \"...\",
  \"focus_keyphrase\": \"...\"
}";
    }

    public function parse_json(string $text): array {
        $text = trim($text);

        // Try exact JSON first
        $json = json_decode($text, true);
        if (is_array($json)) return $json;

        // Try to extract JSON block if AI added extra text
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $json = json_decode($m[0], true);
            if (is_array($json)) return $json;
        }

        return [];
    }
  public function internal_links_prompt(array $data): string {
    $title = (string)($data['title'] ?? '');
    $contentPlain = wp_strip_all_tags((string)($data['content'] ?? ''));
    $excerpt = wp_strip_all_tags((string)($data['excerpt'] ?? ''));

    $candidates = $data['candidates'] ?? [];
    if (!is_array($candidates)) $candidates = [];

    $candLines = [];
    foreach ($candidates as $c) {
        $ct = sanitize_text_field($c['title'] ?? '');
        $cu = esc_url_raw($c['url'] ?? '');
        if ($ct && $cu) $candLines[] = "- {$ct} | {$cu}";
    }
    $candText = implode("\n", array_slice($candLines, 0, 30));

    return
"You're an SEO internal linking assistant for a WordPress website.

Goal:
Suggest 5 internal links to add INSIDE the CURRENT post/page content.

Hard rules (must follow):
- Use ONLY the provided candidate URLs. Do NOT invent URLs.
- Each suggestion MUST use a DIFFERENT URL (no duplicates). Use each URL at most once.
- Anchor must be a phrase (2-6 words) that already appears verbatim in the current content.
- Do NOT link the post to itself.
- Suggestions must be relevant to the current post topic.
- Return STRICT JSON only (no extra text, no markdown).

Current post:
Title: {$title}
Excerpt: {$excerpt}
Content: " . mb_substr($contentPlain, 0, 2200) . "

Candidates (title | url):
{$candText}

Output JSON schema:
{
  \"suggestions\": [
    {\"anchor\":\"...\",\"url\":\"...\",\"title\":\"...\",\"reason\":\"...\"},
    {\"anchor\":\"...\",\"url\":\"...\",\"title\":\"...\",\"reason\":\"...\"},
    {\"anchor\":\"...\",\"url\":\"...\",\"title\":\"...\",\"reason\":\"...\"},
    {\"anchor\":\"...\",\"url\":\"...\",\"title\":\"...\",\"reason\":\"...\"},
    {\"anchor\":\"...\",\"url\":\"...\",\"title\":\"...\",\"reason\":\"...\"}
  ]
}";
}
}