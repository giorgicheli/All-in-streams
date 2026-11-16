# Country flag sources

The website serves 17 original flat PNG country flags locally. They come from [Flagpedia.net](https://flagpedia.net/download) through its [Flagcdn download API](https://flagpedia.net/download/api), based on Wikimedia Commons vector files.

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
| `pt.png` | Portugal | [PNG](https://flagcdn.com/w160/pt.png) | 160 x 107 |
| `ch.png` | Switzerland | [PNG](https://flagcdn.com/w160/ch.png) | 160 x 160 |
| `ar.png` | Argentina | [PNG](https://flagcdn.com/w160/ar.png) | 160 x 100 |
| `nz.png` | New Zealand | [PNG](https://flagcdn.com/w160/nz.png) | 160 x 80 |
| `hu.png` | Hungary | [PNG](https://flagcdn.com/w160/hu.png) | 160 x 80 |
| `us.png` | United States | [PNG](https://flagcdn.com/w160/us.png) | 160 x 84 |
| `at.png` | Austria | [PNG](https://flagcdn.com/w160/at.png) | 160 x 107 |
| `se.png` | Sweden | [PNG](https://flagcdn.com/w160/se.png) | 160 x 100 |
| `no.png` | Norway | [PNG](https://flagcdn.com/w160/no.png) | 160 x 116 |
| `fi.png` | Finland | [PNG](https://flagcdn.com/w160/fi.png) | 160 x 98 |
| `kg.png` | Kyrgyzstan | [PNG](https://flagcdn.com/w160/kg.png) | 160 x 96 |

Exact source URLs, dimensions, verification date and SHA-256 checksums are recorded in [`sources.json`](../public/images/flags/sources.json). These binaries remain excluded from Git and are uploaded with each relevant asset deployment. The version suffix `public-domain-20261104` makes clients request the replacement images instead of cached copies of the previous set.

The 2026-11-16 audience-carousel expansion adds Portugal, Switzerland, Argentina, New Zealand, Hungary, United States, Austria, Sweden, Norway, Finland and Kyrgyzstan. Existing France and Mexico assets remain available for previously authored content.
