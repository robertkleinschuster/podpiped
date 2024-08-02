<?php

declare(strict_types=1);

class Channel
{
    private string $title = '';
    private string $frontend = '';
    private string $language = '';
    private string $description = '';
    private string $toggleDownloadUrl = '';
    private string $copyright = '';
    private string $cover = '';
    private string $items = '';
    private string $author = '';
    public bool $complete = false;
    public bool $downloadEnabled = false;
    /**
     * @param string $title
     * @return Channel
     */
    public function setTitle(string $title): Channel
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param string $author
     * @return Channel
     */
    public function setAuthor(string $author): Channel
    {
        $this->author = htmlentities($author);
        return $this;
    }


    /**
     * @param string $frontend
     * @return Channel
     */
    public function setFrontend(string $frontend): Channel
    {
        $this->frontend = htmlentities($frontend);
        return $this;
    }

    /**
     * @param string $language
     * @return Channel
     */
    public function setLanguage(string $language): Channel
    {
        $this->language = $language;
        return $this;
    }

    /**
     * @param string $description
     * @return Channel
     */
    public function setDescription(string $description): Channel
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @param string $copyright
     * @return Channel
     */
    public function setCopyright(string $copyright): Channel
    {
        $this->copyright = $copyright;
        return $this;
    }

    /**
     * @param string $cover
     * @return Channel
     */
    public function setCover(string $cover): Channel
    {
        $this->cover = htmlentities($cover);
        return $this;
    }

    /**
     * @param string $items
     * @return Channel
     */
    public function setItems(string $items): Channel
    {
        $this->items = $items;
        return $this;
    }

    /**
     * @param bool $downloadEnabled
     */
    public function setDownloadEnabled(bool $downloadEnabled): void
    {
        $this->downloadEnabled = $downloadEnabled;
    }

    /**
     * @param string $toggleDownloadUrl
     */
    public function setToggleDownloadUrl(string $toggleDownloadUrl): void
    {
        $this->toggleDownloadUrl = $toggleDownloadUrl;
    }


    public function __toString()
    {
        $info = '';
        $date = date('Y-m-d H:i:s');

        if ($this->downloadEnabled) {
            $info .= <<<HTML
<br>
Videos werden Serverseitig gespeichert.
<br>
<a href="$this->toggleDownloadUrl">Download deaktivieren.</a>
HTML;
        } else {
            $info .= <<<HTML
<br>
<a href="$this->toggleDownloadUrl">Download aktivieren.</a>
HTML;
        }
        if ($this->complete) {
            $info .= <<<HTML
<br>
Aktualisiert: $date
HTML;
        } else {
            $info .= <<<HTML
<br>
Aktualisierung läuft: $date
HTML;
        }
        return <<<XML
  <channel>
   <title><![CDATA[$this->title]]></title>   
   <link>$this->frontend</link>   
   <language>$this->language</language>   
   <description><![CDATA[
$this->description
<center>
$info
</center>
]]></description>   
   <copyright><![CDATA[$this->copyright]]></copyright>
   <itunes:author>$this->author</itunes:author>
   <image>
    <title><![CDATA[$this->title]]></title>
    <url>$this->cover</url>
    <link>$this->frontend</link>
   </image>
   <itunes:image href="$this->cover"/>
   <podcast:medium>video</podcast:medium>
   $this->items
  </channel>  
XML;
    }
}