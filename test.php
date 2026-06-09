<?php
require 'config/config.php';

function quiz_prompt(string $input, bool $fromImage = false): string
{
    $imageNote = $fromImage
        ? "\nIMPORTANT: All questions must be based directly on the content visible in the uploaded educational image. Perform OCR to extract all text, then generate questions from the extracted content."
        : "\nIMPORTANT: If the input is a topic or keyword, generate quiz questions based on comprehensive academic knowledge of that topic. If the input contains uploaded document text, base all questions ONLY on that content. Never ask for additional documents — always generate the quiz.";
    return <<<PROMPT
You are an advanced Academic Quiz Generator AI. Create EXACTLY 20 multiple-choice exam questions based on the study material below.{$imageNote}

Return ONLY valid JSON — no markdown, no code fences, no extra text before or after.
Exact format:
{"questions":[{"question":"...","choices":["choice text only","choice text only","choice text only","choice text only"],"answer":0,"explanation":"..."}]}

═══════════════════════════════════════════════
STRICT RULES
═══════════════════════════════════════════════

QUESTION GENERATION:
- Generate EXACTLY 20 questions (not fewer, not more).
- Each question must have exactly 4 choices.
- Choices must be PLAIN TEXT ONLY — do NOT include "A.", "B.", "C.", "D." or any letter prefix.
- "answer" is the 0-based index of the correct choice (0 = first, 1 = second, 2 = third, 3 = fourth).
- "explanation" briefly explains why the answer is correct and references the lesson section it came from (1–2 sentences).
- The quiz MUST be generated ONLY from the lesson/source content. Never create questions from external information.

DIFFICULTY DISTRIBUTION:
- Easy (20%): ~4 questions — basic recall of definitions and facts.
- Medium (50%): ~10 questions — understanding, interpretation, and application.
- Hard (30%): ~6 questions — analysis, critical thinking, and synthesis.
- Avoid obvious questions where the answer can be guessed immediately.

QUESTION COVERAGE:
The quiz must cover ALL of these when present in the source material:
- Definitions and key terms
- Key concepts and principles
- Important people and their contributions
- Author statements ("According to...")
- Processes and procedures
- Theories and frameworks
- Examples from the source material
Do NOT generate multiple questions from the same paragraph while ignoring other sections.
Coverage should be balanced across the ENTIRE lesson.

MULTIPLE CHOICE QUALITY:
- All options must appear plausible and believable.
- Only one correct answer per question.
- Distractors should be realistic — avoid silly or obviously wrong options.
- Avoid making the correct answer obviously longer than others.
- Avoid repeating answer position patterns.

ANSWER DISTRIBUTION (CRITICAL):
- Distribute correct answers evenly across positions: A(0)≈25%, B(1)≈25%, C(2)≈25%, D(3)≈25%.
- No single position should have more than 35% of correct answers.
- Randomize answer positions — never create predictable patterns.
- Before finalizing, check the distribution. If unbalanced, redistribute.

QUALITY VERIFICATION (perform before responding):
✓ All questions derived from source material only
✓ No hallucinated or external information
✓ Balanced difficulty distribution
✓ Balanced answer position distribution
✓ Plausible distractors on every question
✓ No obvious answers
✓ Coverage spans the entire source material
✓ Explanations reference the source

Study material / topic:
{$input}
PROMPT;
}

function gemini_curl(string $prompt, string $model, ?array $imageData = null, bool $isJson = false): string
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
         . rawurlencode($model) . ':generateContent';

    // Build parts — image first (if present), then text prompt
    $parts = [];
    if ($imageData !== null) {
        $parts[] = ['inline_data' => ['mime_type' => $imageData['mime_type'], 'data' => $imageData['data']]];
    }
    $parts[] = ['text' => $prompt];

    $config = ['temperature' => 0.7, 'maxOutputTokens' => 8192];
    if ($isJson) {
        $config['responseMimeType'] = 'application/json';
    }

    $payload = json_encode([
        'contents'         => [['parts' => $parts]],
        'generationConfig' => $config,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . GEMINI_API_KEY],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $cerr  = curl_error($ch);
    $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($raw === false) {
        throw new RuntimeException($errno === CURLE_OPERATION_TIMEDOUT ? 'Gemini did not respond in time. Please try again.' : 'Network error contacting Gemini: ' . $cerr);
    }

    $json = json_decode($raw, true);

    if (!empty($json['error'])) {
        $msg  = $json['error']['message'] ?? ('HTTP ' . $http);
        $code = $json['error']['code']    ?? $http;
        throw new RuntimeException('Gemini error (' . $model . '): ' . $msg);
    }

    return $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

try {
    $prompt = quiz_prompt('A lesson about Pi');
    $response = gemini_curl($prompt, 'gemini-1.5-flash', null, true);
    echo "SUCCESS\n";
    echo $response;
} catch (Exception $e) {
    echo "ERROR\n";
    echo $e->getMessage();
}
