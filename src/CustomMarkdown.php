<?php
class CustomMarkdown extends \Michelf\Markdown
{
	protected $simple = false;

	public function __construct($simple = false)
	{
		$this->simple = $simple;
		$this->no_markup = true;
		$this->block_gamut += ['doSpoilers' => 71];
		$this->span_gamut += ['doSearchPermalinks' => 72];
		$this->span_gamut += ['doStrike' => 6];
		$this->span_gamut += ['doUsers' => 7];
		$this->span_gamut += ['doPosts' => 8];
		$this->span_gamut += ['doTags' => 9];
		$this->span_gamut += ['doAutoLinks2' => 29];

		//fix italics/bold in the middle of sentence
		$prop = ['em_relist', 'strong_relist', 'em_strong_relist'];
		for ($i = 0; $i < 3; $i ++)
		{
			$this->{$prop[$i]}[''] = '(?:(?<!\*)' . str_repeat('\*', $i + 1) . '(?!\*)|(?<![a-zA-Z0-9_])' . str_repeat('_', $i + 1) . '(?!_))(?=\S|$)(?![\.,:;]\s)';
			$this->{$prop[$i]}[str_repeat('*', $i + 1)] = '(?<=\S|^)(?<!\*)' . str_repeat('\*', $i + 1) . '(?!\*)';
			$this->{$prop[$i]}[str_repeat('_', $i + 1)] = '(?<=\S|^)(?<!_)' . str_repeat('_', $i + 1) . '(?![a-zA-Z0-9_])';
		}

		parent::__construct();
	}

	protected function formParagraphs($text)
	{
		if ($this->simple)
		{
			$text = preg_replace('/\A\n+|\n+\z/', '', $text);
			$grafs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($grafs as $key => $value)
			{
				if (!preg_match('/^B\x1A[0-9]+B$/', $value))
				{
					$value = $this->runSpanGamut($value);
					$grafs[$key] = $this->unhash($value);
				}
				else
				{
					$grafs[$key] = $this->html_hashes[$value];
				}
			}
			return implode("\n\n", $grafs);
		}
		return parent::formParagraphs($text);
	}

	public static function simpleTransform($text)
	{
		$parser = new self(true);
		return $parser->transform($text);
	}

	protected function doAutoLinks2($text)
	{
		$text = preg_replace_callback('{(?<!<)((https?|ftp):[^\'"><\s(){}]+)}i', [&$this, '_doAutoLinks_url_callback'], $text);
		$text = preg_replace_callback('{(?<![^\s\(\)\[\]])(www\.[^\'"><\s(){}]+)}i', [&$this, '_doAutoLinks_url_callback'], $text);
		return $text;
	}

	protected function _doAnchors_inline_callback($matches)
	{
		if ($matches[3] == '')
			$url = &$matches[4];
		else
			$url = &$matches[3];
		if (!preg_match('/^((https?|ftp):|)\/\//', $url))
			$url = 'http://' . $url;
		return parent::_doAnchors_inline_callback($matches);
	}

	protected function doHardBreaks($text)
	{
		return preg_replace_callback('/\n(?=[\[\]\(\)\w])/', [&$this, '_doHardBreaks_callback'], $text);
	}

	protected function doStrike($text)
	{
		return preg_replace_callback('{(~~|---)([^~]+)\1}', function($x)
		{
			return $this->hashPart('<del>' . $x[2] . '</del>');
		}, $text);
	}

	protected function doSpoilers($text)
	{
		if (is_array($text))
			$text = $this->hashBlock('<span class="spoiler">') . $this->runSpanGamut($text[1]) . $this->hashBlock('</span>');
		return preg_replace_callback('{\[spoiler\]((?:[^\[]|\[(?!\/?spoiler\])|(?R))+)\[\/spoiler\]}is', [__CLASS__, 'doSpoilers'], $text);
	}

	protected function doPosts($text)
	{
		$link = \Chibi\UrlHelper::route('post', 'view', ['id' => '_post_']);
		return preg_replace_callback('/(?:(?<![^\s\(\)\[\]]))@(\d+)/', function($x) use ($link)
		{
			return $this->hashPart('<a href="' . str_replace('_post_', $x[1], $link) . '">' . $x[0] . '</a>');
		}, $text);
	}

	protected function doTags($text)
	{
		$link = \Chibi\UrlHelper::route('post', 'list', ['query' => '_query_']);
		return preg_replace_callback('/(?:(?<![^\s\(\)\[\]]))#([a-zA-Z0-9_-]+)/', function($x) use ($link)
		{
			return $this->hashPart('<a href="' . str_replace('_query_', $x[1], $link) . '">' . $x[0] . '</a>');
		}, $text);
	}

	protected function doUsers($text)
	{
		$link = \Chibi\UrlHelper::route('user', 'view', ['name' => '_name_']);
		return preg_replace_callback('/(?:(?<![^\s\(\)\[\]]))\+([a-zA-Z0-9_-]+)/', function($x) use ($link)
		{
			return $this->hashPart('<a href="' . str_replace('_name_', $x[1], $link) . '">' . $x[0] . '</a>');
		}, $text);
	}

	protected function doSearchPermalinks($text)
	{
		$link = \Chibi\UrlHelper::route('post', 'list', ['query' => '_query_']);
		return preg_replace_callback('{\[search\]((?:[^\[]|\[(?!\/?search\]))+)\[\/search\]}is', function($x) use ($link)
		{
			return $this->hashPart('<a href="' . str_replace('_query_', $x[1], $link) . '">' . $x[1] . '</a>');
		}, $text);
	}
}
