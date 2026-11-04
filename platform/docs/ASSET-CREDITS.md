# Country flag sources

The website serves six original flat PNG country flags locally. They come from [Flagpedia.net](https://flagpedia.net/download) through its [Flagcdn download API](https://flagpedia.net/download/api), based on Wikimedia Commons vector files.

Flagpedia describes these downloads as public domain and free for commercial and non-commercial use. Its backlink is appreciated rather than required. The website therefore does not require a visible flag attribution line. The previous Freepik/Flaticon icons have been replaced and are no longer used by the flag component.

The downloaded files are unchanged 160-pixel-wide PNG originals with their native proportions. The component places them in consistent 3:2 slots with centered `object-fit: contain`, preserving each national flag's full native proportions while keeping the website's minimal rectangular style. Files are locally hosted; page visitors do not make requests to the external flag CDN.

| Local file | Country | Original download | Dimensions |
| --- | --- | --- | --- |
| `de.png` | Germany | [PNG](https://flagcdn.com/w160/de.png) | 160 x 96 |
| `fr.png` | France | [PNG](https://flagcdn.com/w160/fr.png) | 160 x 107 |
| `pl.png` | Poland | [PNG](https://flagcdn.com/w160/pl.png) | 160 x 100 |
| `br.png` | Brazil | [PNG](https://flagcdn.com/w160/br.png) | 160 x 112 |
| `ca.png` | Canada | [PNG](https://flagcdn.com/w160/ca.png) | 160 x 80 |
| `mx.png` | Mexico | [PNG](https://flagcdn.com/w160/mx.png) | 160 x 91 |

Exact source URLs, dimensions, verification date and SHA-256 checksums are recorded in [`sources.json`](../public/images/flags/sources.json). These binaries remain excluded from Git and are uploaded with each relevant asset deployment. The version suffix `public-domain-20261104` makes clients request the replacement images instead of cached copies of the previous set.
