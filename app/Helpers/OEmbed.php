<?php namespace Strimoid\Helpers;

use Cache;
use Config;
use Guzzle;
use GuzzleHttp\Exception\RequestException;

class OEmbed
{
    protected $mimetypes = [
        'audio/' => 'embedAudio',
        'image/' => 'embedImage',
        'video/' => 'embedVideo',
    ];

    public function getThumbnail($url)
    {
        try {
            $query = ['url' => $url];
            $data = Guzzle::get($this->endpoint(), compact('query'))->json();

            $image = array_first($data['links'], function ($key, $value) {
                return $this->isImage($value);
            });

            return data_get($image, 'href', null);
        } catch (RequestException $e) {
        }

        return null;
    }

    public function getThumbnails($url)
    {
        $query = ['url' => $url];
        $data = Guzzle::get($this->endpoint(), compact('query'))->json();

        $data = array_where($data['links'], function ($key, $value) {
            return $this->isImage($value);
        });

        $data = array_pluck($data, 'href');

        return $data;
    }

    protected function isImage($link)
    {
        $rel = data_get($link, 'rel', []);
        $type = data_get($link, 'type');

        if (in_array('thumbnail', $rel)) {
            return true;
        }

        if (in_array('file', $rel) && starts_with($type, 'image')) {
            return true;
        }

        return false;
    }

    public function getEmbedHtml($url, $autoPlay = true)
    {
        $key = md5($url);

        if (! $autoPlay) {
            $key .= '.no-ap';
        }

        $html = Cache::driver('oembed')
            ->rememberForever($key, function () use ($url, $autoPlay) {
                return $this->fetchJson($url, $autoPlay);
            });

        return $html;
    }

    /**
     * @param boolean $autoPlay
     */
    protected function fetchJson($url, $autoPlay)
    {
        try {
            $query = ['ssl' => 'true', 'url' => $url];

            if ($autoPlay) {
                $query['autoplay'] = 'true';
            }

            $data = Guzzle::get($this->endpoint(), compact('query'))->json();

            return $this->processData($data);
        } catch (RequestException $e) {
        }

        return false;
    }

    protected function processData($data)
    {
        if (array_key_exists('html', $data)) {
            return $data['html'];
        }

        foreach ($data['links'] as $link) {
            $rel = data_get($link, 'rel', []);

            if (in_array('file', $rel)) {
                return $this->embedMedia($link);
            }

            if (in_array('image', $rel)) {
                return $this->embedImage($link['href']);
            }
        }

        return false;
    }

    protected function embedMedia($link)
    {
        foreach ($this->mimetypes as $mimetype => $function) {
            if (starts_with($link['type'], $mimetype)) {
                return $this->{$function}($link['href']);
            }
        }

        return false;
    }

    protected function embedAudio($href)
    {
        return '<audio src="'.$href.'"controls autoplay></audio>';
    }

    protected function embedImage($href)
    {
        return '<img src="'.$href.'">';
    }

    protected function embedVideo($href)
    {
        return '<video src="'.$href.'"controls autoplay></audio>';
    }

    /**
     * Return OEmbed API endpoint URL.
     *
     * @return string
     */
    protected function endpoint()
    {
        $host = Config::get('strimoid.oembed');

        return $host.'/iframely';
    }
}
