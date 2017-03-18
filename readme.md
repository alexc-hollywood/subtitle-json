# Subtitle JSON

<a rel="license" href="http://creativecommons.org/licenses/by-sa/4.0/">
  <img alt="Creative Commons License" style="border-width:0" src="https://i.creativecommons.org/l/by-sa/4.0/88x31.png" /></a>
  
Part of the **CineJSON** family of film/TV object notation proposals.

Author: **Alex Coppen** (azcoppen@protonmail.com)

SubJSON is a data model and object notation/interchange syntax for film and TV subtitles. It is offered freely for anyone to use or adapt under a <a rel="license" href="http://creativecommons.org/licenses/by-sa/4.0/">Creative Commons Attribution-ShareAlike 4.0 International License</a>.

## Overview

Subtitles are an under-rated part of movie data, but a source of enormous inefficiency. The most interesting part is a literary transcription of audio information provides a way to make movies **searchable**.

## Existing Standards

There are at least 8 different subtitle formats that are regularly in use. SubRip (.srt) is by far the most widely adopted for downloaded files.

* VOB (DVD)
* PGS (BluRay)
* SubRip (.srt)
* WebVTT (.vtt)
* Substation Alpha (.ass)
* Youtube Subtitles (.sbv)
* JSON (TED.com) Subtitles (.json)
* TTML (.dfxp)

Example .srt entry:

```
119
00:08:51,565 --> 00:08:54,943
So, when Andy Dufresne came to me in 1949

120
00:08:55,027 --> 00:08:58,363
and asked me to smuggle Rita Hayworth
into the prison for him,

121
00:08:58,447 --> 00:09:00,532
I told him, "No problem."
```

## Notable Issues

##### Little or no programmatic access to data

To mine or search subtitle transcription content, access needs to be in a format that is easily deserialized into a simple and usable object model. Subtitles, like screenplays, are formatted in a form of *markup* that describes the timing of an overlay, but nothing about the content inside. Accessing it as *data* allows more sophisticated interchange across programs and platforms, as well as enabling dynamic document generation/manipulation.

##### One file per country?

It makes no sense at all to have multiple country files for the same film. Notwithstanding translations happening at different times in different places, having 1 file per device or release seems considerably more practical.

The sheer amount of diferent versions is baffling:

http://www.yifysubtitles.com/movie-imdb/tt0111161

##### Inconsistent content in releases

A release of a film in France may be different from that in the US. A Blu-Ray Special Edition may be different from the US DVD. A "Director's Cut" may be a totally different length.

##### Inconsistent timings in releases

Each translated file stores its *cues* in milliseconds. Unfortunately, each language file annotates the same dalogue and/or screen text in a less-than-forensic way - meaning entries do not match up across countries, and are usually 100-400ms off. Timecode "start" events are not synchronised at all.


## File Definition Object

The main document container is a simplistic header that provides metadata about the document, as well as information on visual presentation (templates, individual styles).

The timed overlay entries are stored as an array in the **data** attribute.

```json
{
    "id": "33834784375375",
    "title": "The Shawshank Redemption",
    "format": "SubRip",
    "templates": {
        "default": "__CONTENT__",
        "italic": "<i>__CONTENT__<\/i>"
    },
    "styles": {
        "default": "font-style: 10px; line-height: 1; color: #FFF;"
    },
    "data": []
}
```

## Overlay Object

Screen events are an array of overlay objects, ordered ASC by their starting timecode (in milliseconds).

A timecoded subtitle overlay is represented as a common JSON object:

* **trigger**: The time in milliseconds the screen event cue occurs.
* **lang**: The ISO language code of the content to be displayed.
* **atyles**: Which style classes the playback device should apply to the content.
* **templates**: Which display templates the playback device should apply to the content (queued).
* **start**: A mathematical breakdown of the time the screen event should be triggered.
* **end**: A mathematical breakdown of the time the screen event should finish and no longer display.
* **duration**: A mathematical breakdown of the screen event's lifetime.
* **content**: The LTR/RTL string that should be displayed.
* **meta**: Any custom data that can be added to the overlay event for the bbenefit of the playback device,

```json
        {
            "trigger": 4736088,
            "lang": "en",
            "styles": [
                "default"
            ],
            "templates": [
                "default"
            ],
            "start": {
                "time": 4736088,
                "hour": 1,
                "mins": 18,
                "secs": 56,
                "ms": 88
            },
            "end": {
                "time": 4739256,
                "hour": 1,
                "mins": 18,
                "secs": 59,
                "ms": 256
            },
            "duration": {
                "secs": 3.168,
                "ms": 3168
            },
            "content": "There are a hundred different ways to skim off the top.",
            "meta": {
                "original": {
                    "start": "01:18:56,088",
                    "end": "01:18:59,256"
                }
            }
        }
```

## Displaying with Javascript

Example assuming we have JQuery in our display environment (e.g. Node-based program like Electron, or web player like Video.js).

Example to pull all subtitles from an EN subtitle file, and print them to an ordered HTML list:

```js
$( video_player_object ).load(function() {
  $.getJSON( "subtitles/en/RAW_en_the_shawshank_redemption.json", function( file_contents ) {
    var items = [];

    $.each( file_contents.data, function( key, val ) {
      items.push( "<li id='" + key + "'>" + val + "</li>" );
    });

    $( "<ul/>", {
      "class": "my-new-list",
      html: items.join( "" )
    }).appendTo( "body" );

  });
});
```

Example to pull out all Spanish overlay data from a multi-language file that also has EN, FR, PT, and IT entries.

```js
$( video_player_object ).load(function() {
  $.getJSON( "subtitles/multi-language/RAW_multi_the_shawshank_redemption.json", function( file_contents ) {
    var items = [];

    $.each( file_contents.data, function( key, val ) {
      call_some_function_when_time_hits(val.trigger, val.content);
      // etc etc
    });

  });
});
```

## Searching Within NoSQL Storage

Once stored in a back-end NoSQL database (like MongoDB, CouchDB, Neo4j, or even HTML5 localStorage), we suddenly have a way to search the movie.

#### Querying With MongoDB

###### Get all the FR subtitles from a multi-language subtitle file

(assuming we already know the document ID is *4ecc05e55dd98a436ddcc47c*).

```js
 db.subtitles.find({"_id" : ObjectId("4ecc05e55dd98a436ddcc47c"), 'data.lang':'fr'})
```

###### When is the word "skim" mentioned in the film?

```js
db.subtitles.ensureIndex({content:1});
db.subtitles.find(content:"skim");
```

###### What's the average duration a subtitle is displayed for?

```js
db.subtitles.find({"_id" : ObjectId("4ecc05e55dd98a436ddcc47c")}).aggregate(
   [
     {
       $group:
         {
           _id: "$item",
           avgDuration: { $avg: "$duration" }
         }
     }
   ]
)
```

###### How many times does someone say "fuck"?

Using Mongo's `MapReduce` functionality (https://docs.mongodb.com/manual/core/map-reduce/) to look at all occurrences of keywords:

```js
db.subtitles.mapReduce(map, reduce, {out: "word_count"})
```

Result:

```js
db.word_count.find().sort({value:-1})
{ "_id" : "is", "value" : 3 }
{ "_id" : "bad", "value" : 2 }
{ "_id" : "good", "value" : 2 }
{ "_id" : "this", "value" : 2 }
{ "_id" : "neither", "value" : 1 }
{ "_id" : "or", "value" : 1 }
{ "_id" : "something", "value" : 1 }
{ "_id" : "that", "value" : 1 }
```
