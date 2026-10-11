# DOAJ synthetic fixture

Schema source: [official public outgoing journal](https://doaj.github.io/doaj-docs/master/data_models/OutgoingAPIJournal.html), accessed 2026-10-11, and DOAJ/doaj commit `62dd0214cbdcb89cd41a075f840855f4c05d3c1b`.

`journal.json` is invented test data matching that documented v4 shape, not a recorded API response. Names, ID and reserved example domains are synthetic. Mapping version: `doaj-v4-v1`. SHA-256: `d80ebf06a4477872e9ef23527a0296ebb68824f5475901e59d72cd524308dc73`. No credentials, private contact metadata or journal page content. DOAJ terms describe journal metadata under CC0; this fixture copies no real journal facts.

Live access failed before a response on 2026-10-11. No recorded fixture or deployed-schema verification is claimed. Variants and HTTP-page envelopes are constructed in DoajProviderTest.

## Official CSV schema fixture and vocabulary

`journals.csv` is synthetic, not a recorded journal: two identical invented rows with reserved example URLs and DOAJ IDs. Header names and cell representations were verified on 2026-10-11 against the official public CSV download and pinned DOAJ export/question crosswalk. Variants are generated in DoajCsvTest; missing optional columns are intentional coverage. No temporary signed URL or real journal payload is retained.

SHA-256: `467c5539e1237ad3094daa93a481d2cec170e90b25dc44d03df1fe9474ee166f`.

The runtime `csv-vocabulary.json` is a static name/code snapshot, not a runtime dependency: ISO 3166-1 country names/alpha-2 and ISO 639-3 entries with alpha-2 from the already-provisioned host iso-codes JSON, plus DOAJ's explicit Kosovo/XK addition. This follows the official `portality/datasets.py` pycountry algorithm. LCC code/term facts come from DOAJ's `lccSubjects.xml` at commit `62dd0214cbdcb89cd41a075f840855f4c05d3c1b`. Country/language names are exact, case-sensitive; unknown vocabulary is quarantined. No fuzzy match or numeric positional pairing of subjects is used. Refresh the snapshot explicitly when upstream vocabulary changes; no application request downloads vocabularies.
