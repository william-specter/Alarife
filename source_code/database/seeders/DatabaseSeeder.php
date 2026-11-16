<?php

namespace Database\Seeders;

use App\Domain\Publishing\PublishEncyclopedia;
use App\Domain\References\CrossReferenceRegistry;
use App\Models\Article;
use App\Models\Encyclopedia;
use App\Models\EncyclopediaMember;
use App\Models\MediaAsset;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private User $admin;

    private User $editor;

    public function run(): void
    {
        $this->admin = User::firstOrCreate(
            ['email' => 'admin@alarife.test'],
            [
                'name' => 'Alarife Admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_platform_admin' => true,
            ]
        );

        $this->editor = User::firstOrCreate(
            ['email' => 'editor@alarife.test'],
            [
                'name' => 'Mara Velez',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $this->seedAncientCivilizations();
        $this->seedCartography();
        $this->seedBestiary();
        $this->seedAtlas();
        $this->seedForgottenTrades();
        $this->seedCrossReferences();
        $this->publishDemoReleases();
    }

    private function seedAncientCivilizations(): void
    {
        $e = Encyclopedia::firstOrCreate(
            ['slug' => 'ancient-civilizations'],
            [
                'owner_id' => $this->admin->id,
                'title' => 'Ancient Civilizations',
                'subtitle' => 'A visual reference to the first urban worlds',
                'description' => 'From the floodplains of the Nile and the Tigris to the marble cities of the Mediterranean, this edition gathers the places, objects and institutions that shaped the earliest states.',
                'language' => 'en',
                'visibility' => 'public',
                'status' => 'draft',
                'theme_key' => 'editorial',
                'theme_version' => '1.0.0',
                'theme_settings' => ['colors' => ['primary' => '#8c2f24', 'accent' => '#bd8a3c', 'background' => '#f4ede2', 'text' => '#211a15']],
                'cover_style' => 'hero',
                'export_enabled' => true,
            ]
        );

        if ($e->topics()->exists()) {
            return;
        }

        EncyclopediaMember::firstOrCreate(['encyclopedia_id' => $e->id, 'user_id' => $this->editor->id], ['role' => 'editor']);

        $coverA = $this->media($e, 'archive.jpg', 'Sand dunes at the edge of the desert');
        $coverB = $this->media($e, 'marble.jpg', 'Snow-covered limestone ridges');
        $coverC = $this->media($e, 'forum.jpg', 'A vaulted hall of stone and glass');

        $e->covers()->createMany([
            ['media_asset_id' => $coverA->id, 'position' => 1, 'caption' => 'Landscapes that made states possible', 'alt_text' => 'Dunes at the desert edge'],
            ['media_asset_id' => $coverB->id, 'position' => 2, 'caption' => 'Stone, water and the monumental city', 'alt_text' => 'Limestone mountains under snow'],
            ['media_asset_id' => $coverC->id, 'position' => 3, 'caption' => 'Civic space and the architecture of assembly', 'alt_text' => 'A grand vaulted interior'],
        ]);

        $egypt = $e->topics()->create([
            'title' => 'Ancient Egypt',
            'slug' => 'ancient-egypt',
            'description' => 'A civilisation organised around the annual flood of the Nile, with a monumental funerary culture and one of the world’s longest-lived writing systems.',
            'sort_order' => 1,
            'status' => 'published',
        ]);
        $e->topics()->create([
            'parent_id' => $egypt->id,
            'title' => 'The Nile and Society',
            'slug' => 'nile-and-society',
            'description' => 'Irrigation, calendars and the institutions that turned a river into a state.',
            'sort_order' => 1,
            'status' => 'published',
        ]);

        $mesopotamia = $e->topics()->create([
            'title' => 'Mesopotamia',
            'slug' => 'mesopotamia',
            'description' => 'The cities of the Tigris and Euphrates, where writing, law and the archive first took durable form.',
            'sort_order' => 2,
            'status' => 'published',
        ]);
        $greece = $e->topics()->create([
            'title' => 'Ancient Greece',
            'slug' => 'ancient-greece',
            'description' => 'The polis, the theatre, and a civic imagination that still shapes public architecture.',
            'sort_order' => 3,
            'status' => 'published',
        ]);
        $rome = $e->topics()->create([
            'title' => 'The Roman World',
            'slug' => 'roman-world',
            'description' => 'Roads, aqueducts and law: the infrastructure of a Mediterranean empire.',
            'sort_order' => 4,
            'status' => 'published',
        ]);

        $nileImage = $this->media($e, 'nile.jpg', 'A road through a river valley');
        $pyramidImage = $coverA;
        $cuneiformImage = $this->media($e, 'papyrus.jpg', 'Frost on a reed-like surface');
        $athensImage = $this->media($e, 'temple.jpg', 'A green hill with a winding path');
        $forumImage = $coverC;
        $aqueductImage = $this->media($e, 'ocean.jpg', 'A snow-covered mountain range');
        $urImage = $this->media($e, 'scroll.jpg', 'A figure silhouetted at sunset');
        $galleryOne = $this->media($e, 'scroll.jpg', 'A figure silhouetted at sunset');
        $galleryTwo = $this->media($e, 'forest.jpg', 'Fog drifting through a pine forest');

        $this->article($e, $egypt, [
            'title' => 'The Pyramids of Giza',
            'slug' => 'pyramids-of-giza',
            'excerpt' => 'Three monumental tombs, a plateau engineered as a single funerary landscape, and four thousand years of interpretation.',
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(9),
            'featured_image_id' => $pyramidImage->id,
            'content' => $this->doc([
                $this->p('The plateau of Giza is not a collection of isolated monuments but a **designed landscape**. Causeways, quarries, workers’ settlements and cemeteries were laid out together, binding the royal tomb to the river and to the horizon.'),
                $this->h(2, 'A landscape of stone'),
                $this->p('The Great Pyramid of Khufu was raised in the early third millennium BCE. Its limestone casing has largely disappeared, yet the core still preserves the geometry of an extraordinary logistical effort: millions of blocks, quarried, moved and set within a single generation.'),
                $this->image($pyramidImage->id, 'Sand dunes at the desert edge', 'The plateau as a composed ceremonial landscape.'),
                $this->callout('note', 'Reading the evidence', 'Dating follows the conventional chronology used across this edition. Alternative chronologies are noted in the references.'),
                $this->h(3, 'Construction and labour'),
                $this->p('Evidence from workers’ villages has displaced the older image of an undifferentiated mass of forced labour. Skilled crews, rotating levies, bakeries and medical care formed a complex organisation that sustained the building season after season.'),
                $this->bulletList([
                    'Quarries were opened close to the monument to reduce transport distance.',
                    'Water and food moved along a dedicated supply chain from the Nile.',
                    'Cemeteries for workers and officials grew beside the royal complex.',
                ]),
                $this->table(
                    ['Monument', 'Attributed to', 'Approximate date'],
                    [
                        ['Great Pyramid', 'Khufu', 'c. 2560 BCE'],
                        ['Pyramid of Khafre', 'Khafre', 'c. 2520 BCE'],
                        ['Pyramid of Menkaure', 'Menkaure', 'c. 2490 BCE'],
                    ]
                ),
                $this->h(2, 'What the plateau meant'),
                $this->p('The pyramids were instruments of **political theology** as much as engineering. They made visible the king’s mediation between the living world and the divine order, and they organised the labour and resources of the state around that claim.'),
                $this->blockquote('A tomb is also an argument about how a society should remember its dead.'),
                $this->gallery([$galleryOne->id, $galleryTwo->id], 'grid'),
                $this->citation('Lehner, M. *The Complete Pyramids*. Thames & Hudson, 1997.', 'https://example.org/lehner-pyramids', '12 March 2026'),
            ]),
        ]);

        $this->article($e, $egypt, [
            'title' => 'Life Along the Nile',
            'slug' => 'life-along-the-nile',
            'excerpt' => 'The flood, the calendar and the administration that made the river into the spine of an ancient state.',
            'sort_order' => 2,
            'status' => 'published',
            'published_at' => now()->subDays(7),
            'featured_image_id' => $nileImage->id,
            'content' => $this->doc([
                $this->p('Egypt is often described as the gift of the Nile, but the gift required institutions. The annual flood was predicted, measured, recorded and taxed.'),
                $this->h(2, 'Measuring the flood'),
                $this->p('Nilometers turned the height of the river into a language of administration. A good flood meant a larger harvest and a larger assessment; a poor one meant relief, postponement or unrest.'),
                $this->image($nileImage->id, 'A road through a river valley', 'The valley as an engineered corridor of settlement and transport.'),
                $this->callout('tip', 'Look for the calendar', 'Administrative texts often reveal more about the state than royal inscriptions do.'),
                $this->p('The agricultural year structured labour, festivals and the movement of goods. Scribes recorded grain, livestock and people with a precision that made the river’s unpredictability governable.'),
            ]),
        ]);

        $this->article($e, $mesopotamia, [
            'title' => 'Cuneiform and the First Archives',
            'slug' => 'cuneiform-and-the-first-archives',
            'excerpt' => 'Before literature, writing was an instrument of accounts. The archive is one of Mesopotamia’s most durable inventions.',
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(6),
            'featured_image_id' => $cuneiformImage->id,
            'content' => $this->doc([
                $this->p('The earliest written tablets are not poems. They are lists: barley, sheep, labour, rations. Writing emerged from the need to make transactions **portable and inspectable**.'),
                $this->h(2, 'From account to literature'),
                $this->p('Once a notation system can represent language, it can represent anything. Within centuries, scribes were copying hymns, laws and epics.'),
                $this->image($cuneiformImage->id, 'A textured surface resembling a tablet', 'The materiality of the archive: clay, stylus and repetition.'),
                $this->h(3, 'The archive as institution'),
                $this->timeline([
                    ['date' => 'c. 3200 BCE', 'title' => 'Early accounting', 'body' => 'Numerical tablets record goods and labour.'],
                    ['date' => 'c. 2600 BCE', 'title' => 'Administrative expansion', 'body' => 'Palaces and temples maintain dedicated tablet collections.'],
                    ['date' => 'c. 1800 BCE', 'title' => 'Law and literature', 'body' => 'Legal compilations and literary texts are copied and stored.'],
                ]),
                $this->citation('Van De Mieroop, M. *A History of the Ancient Near East*. Wiley, 2015.', '', ''),
            ]),
        ]);

        $this->article($e, $mesopotamia, [
            'title' => 'The Ziggurats of Ur',
            'slug' => 'ziggurats-of-ur',
            'excerpt' => 'A stepped platform, a temple and a claim to cosmic order at the centre of a city.',
            'sort_order' => 2,
            'status' => 'draft',
            'published_at' => null,
            'featured_image_id' => $urImage->id,
            'content' => $this->doc([
                $this->p('The ziggurat of Ur rose as a staged ascent: from street to courtyard, from courtyard to terrace, from terrace toward the sky.'),
                $this->h(2, 'A monument in layers'),
                $this->p('Its mud-brick core was faced with baked brick and bitumen, materials that made the structure both monumental and maintainable.'),
                $this->image($urImage->id, 'A structure on a beach', 'A draft illustration awaiting an archaeological photograph.'),
            ]),
        ]);

        $this->article($e, $greece, [
            'title' => 'Athens and the Invention of Civic Space',
            'slug' => 'athens-and-civic-space',
            'excerpt' => 'The agora, the theatre and the assembly: architecture as a stage for disagreement.',
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(5),
            'featured_image_id' => $athensImage->id,
            'content' => $this->doc([
                $this->p('Athenian democracy was not only a set of procedures. It was a **spatial arrangement** that made certain kinds of speech possible and others difficult.'),
                $this->h(2, 'The agora as a medium'),
                $this->p('Market, court, monument and gossip shared the same ground. The city’s public life was distributed across spaces that could be entered, watched and contested.'),
                $this->image($athensImage->id, 'A green hill with a winding path', 'Civic space as a topography of assembly.'),
                $this->callout('important', 'Beyond the canonical buildings', 'Civic space included workshops, stoas, sanctuaries and the paths between them, not only the monuments that survive.'),
                $this->p('The theatre of Dionysus staged tragedy for a civic audience; the assembly on the Pnyx staged argument for a political one. Both made performance central to legitimacy.'),
            ]),
        ]);

        $this->article($e, $rome, [
            'title' => 'The Roman Forum',
            'slug' => 'the-roman-forum',
            'excerpt' => 'A valley of markets and monuments where law, religion and reputation were negotiated in public.',
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(4),
            'featured_image_id' => $forumImage->id,
            'content' => $this->doc([
                $this->p('The Forum was never a single design. It accumulated: temples, basilicas, honorific columns and shops layered until the valley became a compressed history of the Republic and the Empire.'),
                $this->h(2, 'Law in public'),
                $this->p('Courts met in basilicas; orators spoke from rostra; statues turned reputation into stone. The city’s legal and political life was **rhetorically staged**.'),
                $this->image($forumImage->id, 'A vaulted hall of stone and glass', 'The Forum as a topography of institutions.'),
                $this->table(['Space', 'Function'], [['Basilica', 'Law courts and covered public business'], ['Rostra', 'Oratory and proclamation'], ['Temple', 'Religion and public memory']]),
            ]),
        ]);

        $this->article($e, $rome, [
            'title' => 'Aqueducts and the Engineering of Empire',
            'slug' => 'aqueducts-and-empire',
            'excerpt' => 'Gravity, gradient and maintenance: the infrastructure that made Roman urbanism possible.',
            'sort_order' => 2,
            'status' => 'in_review',
            'published_at' => null,
            'featured_image_id' => $aqueductImage->id,
            'content' => $this->doc([
                $this->p('An aqueduct is a promise that a city can grow beyond its local water. Keeping that promise required surveying, masonry, law and a permanent maintenance budget.'),
                $this->h(2, 'A very long gradient'),
                $this->p('Roman surveyors worked with levels, plumb lines and a disciplined tolerance for slope. Most of the channel ran underground, protected from evaporation and damage.'),
                $this->image($aqueductImage->id, 'Snow-covered mountains', 'Source landscapes for a draft on water infrastructure.'),
            ]),
        ]);

    }

    private function seedCartography(): void
    {
        $e = Encyclopedia::firstOrCreate(
            ['slug' => 'field-notes-on-cartography'],
            [
                'owner_id' => $this->admin->id,
                'title' => 'Field Notes on Cartography',
                'subtitle' => 'Maps, instruments and the invention of the view from above',
                'description' => 'A working notebook on the techniques and politics of mapping: projection, survey, notation and the border.',
                'language' => 'en',
                'visibility' => 'private',
                'status' => 'draft',
                'theme_key' => 'minimal',
                'theme_version' => '1.0.0',
                'cover_style' => 'editorial',
                'export_enabled' => false,
            ]
        );

        if ($e->topics()->exists()) {
            return;
        }

        $cover = $this->media($e, 'map.jpg', 'A misty lake and forest');
        $road = $this->media($e, 'nile.jpg', 'A road threading through a valley');
        $peak = $this->media($e, 'marble.jpg', 'Mountain ridges');
        $e->covers()->createMany([
            ['media_asset_id' => $cover->id, 'position' => 1, 'caption' => 'The view from above is never neutral.'],
            ['media_asset_id' => $this->media($e, 'compass.jpg', 'A sunset over a valley')->id, 'position' => 2, 'caption' => 'Every bearing begins on the ground.'],
        ]);

        $instruments = $e->topics()->create(['title' => 'Instruments', 'slug' => 'instruments', 'description' => 'Compasses, theodolites and the body as a measuring device.', 'sort_order' => 1, 'status' => 'published']);
        $e->topics()->create(['title' => 'Borders', 'slug' => 'borders', 'description' => 'Lines that are argued into existence.', 'sort_order' => 2, 'status' => 'published']);

        $this->article($e, $instruments, [
            'title' => 'The Compass and the Measured Line',
            'slug' => 'compass-and-the-measured-line',
            'excerpt' => 'Before satellites, direction was a relationship between a needle, a map and a walk.',
            'sort_order' => 1,
            'status' => 'draft',
            'published_at' => null,
            'featured_image_id' => $road->id,
            'content' => $this->doc([
                $this->p('A bearing is a promise that a line on paper corresponds to a line underfoot. The instrument only works if the surveyor also knows the ground.'),
                $this->h(2, 'Reading the needle'),
                $this->p('Magnetic north is not true north. Every practical map therefore encodes a **correction** — and an assumption about where the reader stands.'),
                $this->image($road->id, 'A road through a valley', 'A route surveyed by hand before it was drawn.'),
            ]),
        ]);

        $this->article($e, $instruments, [
            'title' => 'Triangulation Without Satellites',
            'slug' => 'triangulation-without-satellites',
            'excerpt' => 'A chain of triangles turned a landscape into a grid and a grid into an empire.',
            'sort_order' => 2,
            'status' => 'draft',
            'published_at' => null,
            'featured_image_id' => $peak->id,
            'content' => $this->doc([
                $this->p('Measure one baseline carefully, then infer everything else. Triangulation is an argument from geometry that scales.'),
                $this->callout('note', 'Notebook', 'This entry is a draft. Add the 1784 survey correspondence before publication.'),
            ]),
        ]);
    }

    private function seedBestiary(): void
    {
        $e = Encyclopedia::firstOrCreate(
            ['slug' => 'a-small-bestiary'],
            [
                'owner_id' => $this->editor->id,
                'title' => 'A Small Bestiary',
                'subtitle' => 'Creatures, habitats and the stories we tell about them',
                'description' => 'A compact natural history of familiar animals, read through folklore, fieldwork and the archive.',
                'language' => 'en',
                'visibility' => 'public',
                'status' => 'draft',
                'theme_key' => 'nocturne',
                'theme_version' => '1.0.0',
                'cover_style' => 'cards',
                'export_enabled' => true,
            ]
        );

        if ($e->topics()->exists()) {
            return;
        }

        $leaf = $this->media($e, 'clock.jpg', 'A leaf with water droplets');
        $frost = $this->media($e, 'papyrus.jpg', 'Frost crystals');
        $forest = $this->media($e, 'forest.jpg', 'A foggy pine forest');
        $beach = $this->media($e, 'observatory.jpg', 'A lifeguard tower on a beach');

        $e->covers()->createMany([
            ['media_asset_id' => $leaf->id, 'position' => 1, 'caption' => 'Small worlds under a leaf'],
            ['media_asset_id' => $frost->id, 'position' => 2, 'caption' => 'What survives the cold'],
            ['media_asset_id' => $forest->id, 'position' => 3, 'caption' => 'The edge of the woods'],
        ]);

        $forestTopic = $e->topics()->create(['title' => 'Forest', 'slug' => 'forest', 'description' => 'Canopy, understory, and the animals that read the woods.', 'sort_order' => 1, 'status' => 'published']);
        $coast = $e->topics()->create(['title' => 'Coast', 'slug' => 'coast', 'description' => 'Tide lines and the creatures that keep time with them.', 'sort_order' => 2, 'status' => 'published']);

        $this->article($e, $forestTopic, [
            'title' => 'The Fox at Dusk',
            'slug' => 'the-fox-at-dusk',
            'excerpt' => 'A creature of edges, equally at home in folklore and in the hedgerow.',
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(3),
            'featured_image_id' => $forest->id,
            'content' => $this->doc([
                $this->p('The fox is a **boundary animal**. It lives where field meets wood, where the domestic and the wild exchange glances.'),
                $this->h(2, 'Reading the edge'),
                $this->p('Urban foxes have made the species a test case for how wildlife adapts to human infrastructure.'),
                $this->image($forest->id, 'Fog in a pine forest', 'The edge habitat, photographed at first light.'),
                $this->callout('tip', 'Field note', 'Look for tracks at the transition between short grass and leaf litter.'),
            ]),
        ]);

        $this->article($e, $coast, [
            'title' => 'Tide Pools and Microcosms',
            'slug' => 'tide-pools-and-microcosms',
            'excerpt' => 'A litre of seawater can hold a whole argument about competition and resilience.',
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(2),
            'featured_image_id' => $beach->id,
            'content' => $this->doc([
                $this->p('Tide pools are laboratories of scarcity. Each retreat of the water resets the terms of survival.'),
                $this->image($beach->id, 'A tower on the shore', 'A marker for the intertidal zone.'),
                $this->citation('Carefoot, T. *Pacific Seashores*. University of Washington Press, 1977.', '', ''),
            ]),
        ]);

        $this->article($e, $coast, [
            'title' => 'A Note on Migration',
            'slug' => 'a-note-on-migration',
            'excerpt' => 'Navigation without maps, and the coast as a calendar.',
            'sort_order' => 2,
            'status' => 'draft',
            'published_at' => null,
            'content' => $this->doc([
                $this->p('Migration makes the coast a temporal place. The same shore is a different habitat in June and in October.'),
            ]),
        ]);

    }

    private function seedAtlas(): void
    {
        $e = Encyclopedia::firstOrCreate(
            ['slug' => 'atlas-of-shared-waters'],
            [
                'owner_id' => $this->admin->id,
                'title' => 'Atlas of Shared Waters',
                'subtitle' => 'Rivers, ports and the routes that connect them',
                'description' => 'A cartographic reader on the waterways that bind inland territories to the sea, and the ports where those routes are negotiated.',
                'language' => 'en',
                'visibility' => 'public',
                'status' => 'draft',
                'theme_key' => 'atlas',
                'theme_version' => '1.0.0',
                'cover_style' => 'hero',
                'export_enabled' => true,
            ]
        );

        if ($e->topics()->exists()) {
            return;
        }

        $delta = $this->media($e, 'ocean.jpg', 'A snow-covered mountain range');
        $port = $this->media($e, 'forum.jpg', 'A large indoor concourse');
        $lake = $this->media($e, 'map.jpg', 'A lake in the mountains');
        $e->covers()->createMany([
            ['media_asset_id' => $delta->id, 'position' => 1, 'caption' => 'Source to sea: one connected system'],
            ['media_asset_id' => $port->id, 'position' => 2, 'caption' => 'Ports as thresholds'],
        ]);

        $rivers = $e->topics()->create(['title' => 'River Systems', 'slug' => 'river-systems', 'description' => 'Headwaters, floodplains and the politics of upstream.', 'sort_order' => 1, 'status' => 'published']);
        $ports = $e->topics()->create(['title' => 'Ports', 'slug' => 'ports', 'description' => 'Where goods, people and regulations change hands.', 'sort_order' => 2, 'status' => 'published']);

        $this->article($e, $rivers, [
            'title' => 'Reading a River Basin',
            'slug' => 'reading-a-river-basin',
            'excerpt' => 'A basin is a unit of ecology, a unit of law and, often, a unit of conflict.',
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(8),
            'featured_image_id' => $lake->id,
            'content' => $this->doc([
                $this->p('Water does not respect administrative boundaries. A river basin therefore exposes the gap between the map of jurisdiction and the map of consequence.'),
                $this->h(2, 'Upstream and downstream'),
                $this->p('Every diversion is also a decision about who will receive less water later.'),
                $this->image($lake->id, 'A mountain lake', 'A headwater lake, the beginning of a downstream politics.'),
                $this->table(['Term', 'Meaning'], [['Catchment', 'The area drained by a river system'], ['Riparian', 'Of the bank or shore'], ['Delta', 'The depositional mouth of a river']]),
            ]),
        ]);

        $this->article($e, $ports, [
            'title' => 'The Port as a Threshold',
            'slug' => 'the-port-as-a-threshold',
            'excerpt' => 'Customs, cranes and the architecture of exchange.',
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(1),
            'featured_image_id' => $port->id,
            'content' => $this->doc([
                $this->p('A port is a machine for converting one jurisdiction into another. Its architecture is a sequence of controlled crossings.'),
                $this->image($port->id, 'A concourse interior', 'The interior of a port is a sequence of thresholds.'),
                $this->callout('note', 'Atlas convention', 'Port entries are keyed to the river basin they serve, not to the political border they sit on.'),
            ]),
        ]);

    }

    private function seedForgottenTrades(): void
    {
        $e = Encyclopedia::firstOrCreate(
            ['slug' => 'dictionary-of-forgotten-trades'],
            [
                'owner_id' => $this->admin->id,
                'title' => 'Dictionary of Forgotten Trades',
                'subtitle' => 'Occupations that shaped objects and cities',
                'description' => 'A lexicon of crafts, tools and workshop words that have fallen out of everyday use but still structure the built world.',
                'language' => 'en',
                'visibility' => 'private',
                'status' => 'draft',
                'theme_key' => 'classic',
                'theme_version' => '1.0.0',
                'cover_style' => 'editorial',
                'export_enabled' => false,
            ]
        );

        if ($e->topics()->exists()) {
            return;
        }

        $clock = $this->media($e, 'clock.jpg', 'A pocket watch');
        $desk = $this->media($e, 'parthenon.jpg', 'A wooden desk with a laptop');
        $e->covers()->create(['media_asset_id' => $clock->id, 'position' => 1, 'caption' => 'Timekeeping as a craft.']);

        $trades = $e->topics()->create(['title' => 'Workshop Trades', 'slug' => 'workshop-trades', 'description' => 'Words from the bench, the forge and the lathe.', 'sort_order' => 1, 'status' => 'published']);

        $this->article($e, $trades, [
            'title' => 'The Clockmaker’s Bench',
            'slug' => 'the-clockmakers-bench',
            'excerpt' => 'A vocabulary of escapements, jewels and the patience of small adjustments.',
            'sort_order' => 1,
            'status' => 'draft',
            'published_at' => null,
            'featured_image_id' => $clock->id,
            'content' => $this->doc([
                $this->p('A clock is a argument made of brass: that regularity can be manufactured and maintained.'),
                $this->image($clock->id, 'A pocket watch', 'The bench as a place where time is adjusted by hand.'),
            ]),
        ]);

        $this->article($e, $trades, [
            'title' => 'The Copyist',
            'slug' => 'the-copyist',
            'excerpt' => 'Before duplication, accuracy was a form of skilled labour.',
            'sort_order' => 2,
            'status' => 'draft',
            'published_at' => null,
            'featured_image_id' => $desk->id,
            'content' => $this->doc([
                $this->p('The copyist’s body was part of the technology: posture, ink, light and the discipline of the line.'),
                $this->image($desk->id, 'A writing desk', 'A modern desk standing in for the copyist’s table.'),
            ]),
        ]);
    }

    private function seedCrossReferences(): void
    {
        $ancient = Encyclopedia::where('slug', 'ancient-civilizations')->first();
        $atlas = Encyclopedia::where('slug', 'atlas-of-shared-waters')->first();
        $bestiary = Encyclopedia::where('slug', 'a-small-bestiary')->first();

        if (! $ancient || ! $atlas || ! $bestiary) {
            return;
        }

        // Reference validation requires published targets. The publish loop runs immediately
        // afterwards, so this only bridges the seeding order.
        foreach ([$ancient, $atlas, $bestiary] as $work) {
            if ($work->status !== 'published') {
                $work->update(['status' => 'published']);
            }
        }

        $registry = app(CrossReferenceRegistry::class);

        $atlasRiver = $atlas->articles()->where('slug', 'reading-a-river-basin')->first();
        $atlasPorts = $atlas->topics()->where('slug', 'ports')->first();
        $bestiaryTides = $bestiary->articles()->where('slug', 'tide-pools-and-microcosms')->first();
        $ancientPyramids = $ancient->articles()->where('slug', 'pyramids-of-giza')->first();
        $ancientEgypt = $ancient->topics()->where('slug', 'ancient-egypt')->first();
        $bestiaryFox = $bestiary->articles()->where('slug', 'the-fox-at-dusk')->first();

        $references = [];

        if ($atlasRiver) {
            $references[] = ['target_type' => 'article', 'target_id' => $atlasRiver->id, 'target_encyclopedia_id' => $atlas->id, 'note' => 'Read how river basins become political geography.'];
        }
        if ($atlasPorts) {
            $references[] = ['target_type' => 'topic', 'target_id' => $atlasPorts->id, 'target_encyclopedia_id' => $atlas->id, 'note' => null];
        }
        if ($bestiaryTides) {
            $references[] = ['target_type' => 'article', 'target_id' => $bestiaryTides->id, 'target_encyclopedia_id' => $bestiary->id, 'note' => 'A modern microcosm of scarcity and resilience.'];
        }
        if ($references !== []) {
            $registry->persist($ancient, $references, $this->admin);
        }

        if ($ancientPyramids && $bestiaryFox) {
            $registry->persist($atlas, [
                ['target_type' => 'article', 'target_id' => $ancientPyramids->id, 'target_encyclopedia_id' => $ancient->id, 'note' => 'Monumental landscapes and the state.'],
                ['target_type' => 'article', 'target_id' => $bestiaryFox->id, 'target_encyclopedia_id' => $bestiary->id, 'note' => null],
            ], $this->admin);
        }

        if ($ancientEgypt) {
            $registry->persist($bestiary, [
                ['target_type' => 'topic', 'target_id' => $ancientEgypt->id, 'target_encyclopedia_id' => $ancient->id, 'note' => 'Early states and the animals they imagined.'],
            ], $this->editor);
        }
    }

    private function publishDemoReleases(): void
    {
        $releases = [
            ['ancient-civilizations', $this->admin, 'First public edition.'],
            ['a-small-bestiary', $this->editor, 'Opening edition of the bestiary.'],
            ['atlas-of-shared-waters', $this->admin, 'Inaugural atlas.'],
        ];

        foreach ($releases as [$slug, $user, $notes]) {
            $encyclopedia = Encyclopedia::where('slug', $slug)->first();
            if ($encyclopedia && $encyclopedia->publications()->count() === 0) {
                app(PublishEncyclopedia::class)($encyclopedia, $user, $notes);
            }
        }
    }

    private function article(Encyclopedia $encyclopedia, Topic $topic, array $data): Article
    {
        return $encyclopedia->articles()->create([
            'topic_id' => $topic->id,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'],
            'content' => $data['content'],
            'content_schema_version' => 1,
            'featured_image_id' => $data['featured_image_id'] ?? null,
            'status' => $data['status'],
            'sort_order' => $data['sort_order'],
            'revision_number' => 1,
            'created_by' => $this->editor->id,
            'updated_by' => $this->editor->id,
            'published_at' => $data['published_at'] ?? null,
        ]);
    }

    private function media(Encyclopedia $encyclopedia, string $file, string $alt): MediaAsset
    {
        $existing = $encyclopedia->media()->where('filename', $file)->first();
        if ($existing) {
            return $existing;
        }

        $source = database_path('seeders/images/'.$file);
        $contents = (string) file_get_contents($source);
        $path = 'media/'.$encyclopedia->id.'/seed-'.Str::slug(pathinfo($file, PATHINFO_FILENAME)).'.jpg';
        Storage::disk('public')->put($path, $contents);
        $dimensions = getimagesize($source) ?: [1600, 1000];

        return $encyclopedia->media()->create([
            'uploaded_by' => $this->admin->id,
            'disk' => 'public',
            'path' => $path,
            'filename' => $file,
            'mime_type' => 'image/jpeg',
            'size' => strlen($contents),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'alt_text' => $alt,
            'caption' => null,
            'checksum' => hash('sha256', $contents),
        ]);
    }

    private function doc(array $content): array
    {
        return ['type' => 'doc', 'content' => $content];
    }

    private function p(string $text): array
    {
        return ['type' => 'paragraph', 'content' => $this->inline($text)];
    }

    private function h(int $level, string $text): array
    {
        return ['type' => 'heading', 'attrs' => ['level' => $level], 'content' => [['type' => 'text', 'text' => $text]]];
    }

    private function inline(string $text): array
    {
        $parts = preg_split('/(\*\*[^*]+\*\*|\*[^*]+\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$text];
        $nodes = [];
        foreach ($parts as $part) {
            if (Str::startsWith($part, '**') && Str::endsWith($part, '**')) {
                $nodes[] = ['type' => 'text', 'text' => substr($part, 2, -2), 'marks' => [['type' => 'bold']]];
            } elseif (Str::startsWith($part, '*') && Str::endsWith($part, '*')) {
                $nodes[] = ['type' => 'text', 'text' => substr($part, 1, -1), 'marks' => [['type' => 'italic']]];
            } else {
                $nodes[] = ['type' => 'text', 'text' => $part];
            }
        }

        return $nodes;
    }

    private function image(int $mediaId, string $alt, string $caption): array
    {
        return ['type' => 'image', 'attrs' => ['mediaId' => $mediaId, 'alt' => $alt, 'caption' => $caption]];
    }

    private function gallery(array $mediaIds, string $layout): array
    {
        return ['type' => 'gallery', 'attrs' => ['layout' => $layout, 'images' => array_map(fn ($id) => [
            'mediaId' => $id, 'alt' => 'Archive image', 'caption' => '',
        ], $mediaIds)]];
    }

    private function callout(string $tone, string $title, string $text): array
    {
        return ['type' => 'callout', 'attrs' => ['tone' => $tone, 'title' => $title], 'content' => [$this->p($text)]];
    }

    private function bulletList(array $items): array
    {
        return ['type' => 'bulletList', 'content' => array_map(
            fn ($item) => ['type' => 'listItem', 'content' => [$this->p($item)]],
            $items
        )];
    }

    private function blockquote(string $text): array
    {
        return ['type' => 'blockquote', 'content' => [$this->p($text)]];
    }

    private function table(array $headers, array $rows): array
    {
        $content = [
            ['type' => 'tableRow', 'content' => array_map(fn ($cell) => [
                'type' => 'tableHeader', 'content' => [$this->p($cell)],
            ], $headers)],
        ];
        foreach ($rows as $row) {
            $content[] = ['type' => 'tableRow', 'content' => array_map(fn ($cell) => [
                'type' => 'tableCell', 'content' => [$this->p($cell)],
            ], $row)];
        }

        return ['type' => 'table', 'content' => $content];
    }

    private function timeline(array $items): array
    {
        return ['type' => 'timeline', 'attrs' => ['items' => array_map(fn ($item) => [
            'date' => $item['date'], 'title' => $item['title'], 'body' => $item['body'],
        ], $items)]];
    }

    private function citation(string $source, string $url, string $accessed): array
    {
        return ['type' => 'citation', 'attrs' => ['source' => $source, 'url' => $url, 'accessed' => $accessed]];
    }
}
