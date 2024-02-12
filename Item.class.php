<?php

declare(strict_types=1);

class Item
{
    private string $title = '';
    private string $episodeType = '';
    private string $summary = '';
    private string $uploaderUrl = '';
    private string $description = '';
    private string $thumbnail = '';
    private string $duration = '';
    private string $chaptersUrl = '';
    private string $uploaderName = '';
    private string $uploaderFeedUrl = '';
    private string $date = '';
    private string $videoId = '';
    private string $videoUrl = '';
    private string $size = '';
    private string $mimeType;
    private string $url = '';
    private string $hls = '';
    private string $downloadFilename = '';

    public bool $complete = false;

    /**
     * @param string $title
     * @return Item
     */
    public function setTitle(string $title): Item
    {
        $this->title = htmlentities($title);
        return $this;
    }

    /**
     * @param string $hls
     * @return Item
     */
    public function setHls(string $hls): Item
    {
        $this->hls = htmlentities($hls);
        return $this;
    }


    /**
     * @param string $episodeType
     * @return Item
     */
    public function setEpisodeType(string $episodeType): Item
    {
        $this->episodeType = $episodeType;
        return $this;
    }

    /**
     * @param string $uploaderFeedUrl
     * @return Item
     */
    public function setUploaderFeedUrl(string $uploaderFeedUrl): Item
    {
        $this->uploaderFeedUrl = htmlentities($uploaderFeedUrl);
        return $this;
    }


    /**
     * @param string $summary
     * @return Item
     */
    public function setSummary(string $summary): Item
    {
        $this->summary = $summary;
        return $this;
    }

    /**
     * @param string $uploaderUrl
     * @return Item
     */
    public function setUploaderUrl(string $uploaderUrl): Item
    {
        $this->uploaderUrl = htmlentities($uploaderUrl);
        return $this;
    }

    /**
     * @param string $description
     * @return Item
     */
    public function setDescription(string $description): Item
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @param string $thumbnail
     * @return Item
     */
    public function setThumbnail(string $thumbnail): Item
    {
        $this->thumbnail = htmlentities($thumbnail);
        return $this;
    }

    /**
     * @param string $duration
     * @return Item
     */
    public function setDuration(string $duration): Item
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * @param string $chaptersUrl
     * @return Item
     */
    public function setChaptersUrl(string $chaptersUrl): Item
    {
        $this->chaptersUrl = htmlentities($chaptersUrl);
        return $this;
    }

    /**
     * @param string $uploaderName
     * @return Item
     */
    public function setUploaderName(string $uploaderName): Item
    {
        $this->uploaderName = htmlentities($uploaderName);
        return $this;
    }

    /**
     * @param string $date
     * @return Item
     */
    public function setDate(string $date): Item
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @param string $url
     * @return Item
     */
    public function setUrl(string $url): Item
    {
        $this->url = htmlentities($url);
        return $this;
    }


    /**
     * @param string $videoId
     * @return Item
     */
    public function setVideoId(string $videoId): Item
    {
        $this->videoId = $videoId;
        return $this;
    }

    /**
     * @param string $videoUrl
     * @return Item
     */
    public function setVideoUrl(string $videoUrl): Item
    {
        $this->videoUrl = htmlentities($videoUrl);
        return $this;
    }

    /**
     * @param string $size
     * @return Item
     */
    public function setSize(string $size): Item
    {
        $this->size = $size;
        return $this;
    }

    /**
     * @param string $mimeType
     * @return Item
     */
    public function setMimeType(string $mimeType): Item
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    /**
     * @param bool $complete
     */
    public function setComplete(bool $complete): void
    {
        $this->complete = $complete;
    }

    /**
     * @param string $downloadFilename
     */
    public function setDownloadFilename(string $downloadFilename): void
    {
        $this->downloadFilename = $downloadFilename;
    }

    public function __toString()
    {

        $videoLink = '';
        if ($this->complete && $this->downloadFilename && $this->videoUrl) {
            $videoLink = <<<HTML
<a href="$this->videoUrl" target="_blank" download="$this->downloadFilename">Herunterladen ⬇️</a>
<br>
HTML;
        }
        return <<<XML
<item>
    <title><![CDATA[$this->title]]></title>   
    <itunes:episodeType>$this->episodeType</itunes:episodeType>
    <itunes:summary><![CDATA[$this->summary]]></itunes:summary>  
    <description><![CDATA[
    <center>
    $this->summary
    <br>
    $videoLink
    <a href="$this->uploaderUrl">zum Kanal</a>
    <br>
    <a href="$this->uploaderFeedUrl">Kanal Podcast</a>
    <br>
    ＿＿＿＿＿＿＿＿＿＿＿＿＿＿
    <br>
    <br>
    </center>
    $this->description
    ]]></description>  
    <itunes:duration>$this->duration</itunes:duration>
    <podcast:chapters url="$this->chaptersUrl" type="application/json+chapters"/>
    <podcast:person><![CDATA[$this->uploaderName]]></podcast:person>
    <pubDate>$this->date</pubDate>
    <link>$this->url</link>
    <guid>$this->videoId</guid>
    <enclosure url="$this->videoUrl" length="$this->size" type="$this->mimeType" />   
</item>
XML;
    }
}