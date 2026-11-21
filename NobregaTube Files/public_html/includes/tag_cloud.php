<?php
function tag_frequency(array $videos, bool $weightByViews = false): array
{
	$frequencies = [];
	foreach ($videos as $video) {
		$tags = preg_split('/\s+/', trim((string)$video['tags']), -1, PREG_SPLIT_NO_EMPTY);
		$tagsInVideo = [];
		foreach ($tags as $tag) {
			$key = strtolower($tag);
			$tagsInVideo[$key] = $tagsInVideo[$key] ?? $tag;
		}

		$weight = $weightByViews ? max(1, (int)($video['views'] ?? 0)) : 1;
		foreach ($tagsInVideo as $key => $tag) {
			if (!isset($frequencies[$key])) {
				$frequencies[$key] = ['tag' => $tag, 'frequency' => 0];
			}
			$frequencies[$key]['frequency'] += $weight;
		}
	}

	uasort($frequencies, static function (array $left, array $right): int {
		return $right['frequency'] <=> $left['frequency'] ?: strcasecmp($left['tag'], $right['tag']);
	});

	return array_slice($frequencies, 0, 100, true);
}

function tag_font_size(int $frequency, int $maximum): int
{
	if ($maximum < 1) {
		return 10;
	}

	return 10 + (int)round(18 * $frequency / $maximum);
}
