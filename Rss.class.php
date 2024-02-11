<?php

declare(strict_types=1);

require_once "Channel.class.php";

class Rss
{
    private Channel $channel;

    /**
     * @param Channel $channel
     */
    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
    }

    public function __toString(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
 <rss version="2.0" 
 xmlns:podcast="https://podcastindex.org/namespace/1.0" 
 xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" 
 xmlns:content="http://purl.org/rss/1.0/modules/content/">
  $this->channel   
 </rss>
XML;
    }
}