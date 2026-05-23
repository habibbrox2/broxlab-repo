<?php
/**
 * AlQuran Model - Al-Quran Playlist & Track Management
 *
 * Features:
 * - CRUD for Surahs, Playlists, Tracks
 * - Live fetches from external API (api.quran.com) with local DB caching
 * - Prepared statements throughout
 * - Soft Deletes Where needed
 *
 * Use: new AlQuranModel($mysqli)
 * External: https://api.quran.com/api/v4
 */

class AlQuranModel
{
    private mysqli $mysqli;
    private string $apiBase;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli   = $mysqli;
        $this->apiBase  = 'https://api.quran.com/api/v4';
    }

    // ======================================================================
    // API FETCH HELPERS
    // ======================================================================

    /**
     * GET a URL with a short HTTP timeout and return decoded JSON.
     * @return array{success:bool, data:mixed, status:int, error:string}
     */
    private function fetchJson(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'BroxLab-AlQuran/1.0',
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'data' => null, 'status' => 0, 'error' => $err ?: 'cURL error'];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'data' => null, 'status' => $httpCode, 'error' => 'Invalid JSON'];
        }

        return ['success' => $httpCode === 200, 'data' => $decoded, 'status' => $httpCode, 'error' => $httpCode !== 200 ? "HTTP $httpCode" : ''];
    }

    // ======================================================================
    // SURAH MANAGEMENT
    // ======================================================================

    /**
     * Fetch all surahs from local DB.
     * @return list<array{id:int,surah_number:int,name_arabic:string,name_bangla:string,name_english:string,total_ayahs:int,surah_type:string}>
     */
    public function getAllSurahs(): array
    {
        // Try cache first
        $cached = $this->getCachedSurahs();
        if (!empty($cached)) {
            return $cached;
        }

        // Empty table – fetch from api.quran.com and seed
        $this->seedSurahsFromApi();
        return $this->getAllSurahs();
    }

    /**
     * Get a single surah by number.
     * @return array|null
     */
    public function getSurahByNumber(int $surahNumber): ?array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT id, surah_number, name_arabic, name_bangla, name_english, total_ayahs, surah_type, ayah_start, ayah_end, ruku_count FROM quran_surahs WHERE surah_number = ? LIMIT 1'
        );
        $stmt->bind_param('i', $surahNumber);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Get a single surah by ID (本地 DB).
     * @return array|null
     */
    public function getSurahById(int $surahId): ?array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT id, surah_number, name_arabic, name_bangla, name_english, total_ayahs, surah_type, ayah_start, ayah_end, ruku_count FROM quran_surahs WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $surahId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Return all cached surahs.
     * @return list<array>
     */
    private function getCachedSurahs(): array
    {
        $result = $this->mysqli->query(
            'SELECT id, surah_number, name_arabic, name_bangla, name_english, total_ayahs, surah_type FROM quran_surahs ORDER BY surah_number ASC'
        );
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Fetch surah list from api.quran.com and store in local DB.
     */
    private function seedSurahsFromApi(): void
    {
        $resp = $this->fetchJson($this->apiBase . '/chapters');
        if (!$resp['success'] || empty($resp['data']['chapters'])) {
            logError('AlQuranModel: API surah list fetch failed: ' . ($resp['error'] ?? ''));
            return;
        }

        $chapters = $resp['data']['chapters'];
        $this->mysqli->begin_transaction();
        try {
            $stmt = $this->mysqli->prepare(
                'INSERT INTO quran_surahs (surah_number, name_arabic, name_bangla, name_english, total_ayahs, surah_type)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name_arabic=VALUES(name_arabic), name_bangla=VALUES(name_bangla), name_english=VALUES(name_english), total_ayahs=VALUES(total_ayahs), surah_type=VALUES(surah_type)'
            );

            foreach ($chapters as $ch) {
                $bnName = $this->getBanglaSurahName((int)($ch['id'] ?? $ch['chapter_number'] ?? 0));
                if ($bnName === '') {
                    continue;
                }
                $stmt->bind_param(
                    'isssis',
                    $ch['id'],
                    $ch['name_arabic'],
                    $bnName,
                    $ch['name_simple'] ?? $ch['englishName'] ?? '',
                    $ch['verses_count'] ?? 0,
                    $ch['revelation_place'] === 'Mecca' ? 'meccan' : 'medinan'
                );
                $stmt->execute();
            }
            $stmt->close();
            $this->mysqli->commit();
        } catch (Throwable $e) {
            $this->mysqli->rollback();
            logError('AlQuranModel: SQL error-seedingSurahs: ' . $e->getMessage());
        }
    }

    /**
     * Map surah number → Bangla name (all 114).
     * Returns empty string if unknown (skip from DB).
     */
    private function getBanglaSurahName(int $num): string
    {
        $names = [
            1  => 'সূরা আল-ফাতিহা',
            2  => 'সূরা আল-বাকারা',
            3  => 'সূরা আল-ই-ইমরান',
            4  => 'সূরা.annisa',
            5  => 'সূরা আল-মায়িদা',
            6  => 'সূরা আল-আনআম',
            7  => 'সূরা আল-আরাফ',
            8  => 'সূরা আল-আনফাল',
            9  => 'সূরা আত-তাওবাহ',
            10 => 'সূরা ইউনুস',
            11 => 'সূরা হুদ',
            12 => 'সূরা ইউসুফ',
            13 => 'সূরা রা\'দ',
            14 => 'সূরা ইবরাহিম',
            15 => 'সূরা আল-হিজর',
            16 => 'সূরা.ann_Nahl',
            17 => 'সূরা আল-_isra',
            18 => 'সূরা আল-কাহফ',
            19 => 'সূরা মারইয়াম',
            20 => 'সূরা Toy-Ha',
            21 => 'সূরা আল-আনবীয়া',
            22 => 'সূরা আল-হজ্জ',
            23 => 'সূরা আল-মু\'মিনুন',
            24 => 'সূরা.annoor',
            25 => 'সূরা আল-ফুরকান',
            26 => 'সূরা.al-Šu`āra',
            27 => 'সূরা.ann_Naml',
            28 => 'সূরা আল-ক্বাসাস',
            29 => 'সূরা আল-`ankabūt',
            30 => 'সূরা আল-রূম',
            31 => 'সূরা লোকমান',
            32 => 'সূরা সাজদাহ',
            33 => 'সূরা.al-Ahzāb',
            34 => 'সূরা সাবা',
            35 => 'সূরা ফাতির',
            36 => 'সূরা ইয়াহসীন',
            37 => 'সূরা আস-সাফফাত',
            38 => 'সূরা সোয়াদ',
            39 => 'সূরা আল-মু\'মার',
            40 => 'সূরা আল-ফুসল',
            41 => 'সূরা_fussīlāt',
            42 => 'সূরা আশ-শূরা',
            43 => 'সূরা আল-জুariah',
            44 => 'সূরা আল-দুখন',
            45 => 'সূরা আল-জাথিয়াহ',
            46 => 'সূরা.al-Ahqāf',
            47 => 'সূরা মুহাম্মদ',
            48 => 'সূরা আল-ফাতহ',
            49 => 'সূরা আল-হুজুরাত',
            50 => 'সূরা কাফ',
            51 => 'সূরা Adh-Dhariyat',
            52 => 'সূরা_ath-Thur',
            53 => 'সূরা.al-Najm',
            54 => 'সূরা.al-Qamar',
            55 => 'সূরা.ar-Rahman',
            56 => 'সূরা.al-Waqi`ah',
            57 => 'সূরা.al-Hadīd',
            58 => 'সূরা.al-Mujādilah',
            59 => 'সূরা.al-Hashr',
            60 => 'সূরা.al-Mumtahanah',
            61 => 'সূরা.as-Saff',
            62 => 'সূরা.al-Jumu`ah',
            63 => 'সূরা.al-Munāfiqūn',
            64 => 'সূরা.al-Taghabun',
            65 => 'সূরা.at-Talāq',
            66 => 'সূরা.at-Tahrīm',
            67 => 'সূরা.al-Mulk',
            68 => 'সূরা.al-Qalam',
            69 => 'সূরা.al-Hāqqah',
            70 => 'সূরা.at-Tāriq',
            71 => 'সূরা.ann_Nūh',
            72 => 'সূরা.al-Jinn',
            73 => 'সূরা.al-Muzzammil',
            74 => 'সূরা.al-Muddaththir',
            75 => 'সূরা.al-Qiyāmāh',
            76 => 'সূরা.al-Insān',
            77 => 'সূরা.al-Mursalāt',
            78 => 'সূরা.ann_Naba`',
            79 => 'সূরা.ann_Nāzi`āt',
            80 => 'সূরা `Abasah',
            81 => 'সূরা Takwīr',
            82 => 'সূরা al-Infitār',
            83 => 'সূরা al-Mutaffifīn',
            84 => 'সূরা al-Inshiqāq',
            85 => 'সূরা al-Burūj',
            86 => 'সূরা at-Tāriq',
            87 => 'সূরা al-`Ala',
            88 => 'সূরা al-Ghāshiyah',
            89 => 'সূরা al-Fajr',
            90 => 'সূরা al-Balad',
            91 => 'সূরা ash-Shams',
            92 => 'সূরা al-Layl',
            93 => 'সূরা ash-Shu`arā',
            94 => 'সূরা az-Zuha',
            95 => 'সূরা al-Humazah',
            96 => 'সূরা al-Fatihah',
            97 => 'সূরা al-Qadr',
            98 => 'সূরা al-Bayyīnah',
            99 => 'সূরা al-Zalzalah',
            100=> 'সূরা al-`Ādiyāt',
            101=> 'সূরা al-Qāri`ah',
            102=> 'সূরা at-Takāthur',
            103=> 'সূরা al-`Aşr',
            104=> 'সূরা al-Hāmah',
            105=> 'সূরা al-Humazah',
            106=> 'সূরা al-Fīl',
            107=> 'সূরা al-Mā`ūn',
            108=> 'সূরা Quraysh',
            109=> 'সূরা al-Mā`ūn',
            110=> 'সূরা an-Naşr',
            111=> 'সূরা al-Masad',
            112=> 'সূরা.al-Ikhlās',
            113=> 'সূরা.al-Falāq',
            114=> 'সূরা.ann_Nās',
        ];
        return $names[$num] ?? '';
    }

    // ======================================================================
    // AYAH READING (from api.quran.com)
    // ======================================================================

    /**
     * Fetch the ayahs of a surah directly from the Quran API.
     * Caches in the Twig template scope; does NOT write to DB here.
     * @return array{success:bool, ayahs: array<int, array{id:number,text_uthmani:string,number_in_surah:int,translate_bangla:string}>, error:string, status:int}
     */
    public function fetchAyahsFromApi(int $surahNumber): array
    {
        $resp = $this->fetchJson(
            $this->apiBase . '/quran/verses/uthmani?chapter_number=' . $surahNumber
        );

        if (!$resp['success']) {
            return [
                'success' => false,
                'ayahs'   => [],
                'error'   => $resp['error'] ?: 'Failed to fetch surah',
                'status'  => $resp['status'],
            ];
        }

        $rawAyahs  = $resp['data']['verses'] ?? [];
        $ayahs     = [];
        $fallbackBn = $this->getBanglaSurahName($surahNumber);

        foreach ($rawAyahs as $v) {
            $ayahNum = (int)($v['verse_number'] ?? $v['id'] ?? 0);
            if ($ayahNum <= 0) continue;

            $ayahs[] = [
                'id'              => (int)($v['id'] ?? $ayahNum),
                'text_uthmani'     => (string)($v['text_uthmani'] ?? $v['text_imlaei'] ?? ''),
                'number_in_surah'  => $ayahNum,
                'translate_bangla' => $fallbackBn, // will be enriched client-side
            ];
        }

        return ['success' => true, 'ayahs' => $ayahs, 'error' => '', 'status' => 200];
    }

    // ======================================================================
    // PLAYLISTS
    // ======================================================================

    /**
     * Get all playlists (public only by default; pass true $includePrivate for admin).
     * @return list<array>
     */
    public function getPlaylists(bool $includePrivate = false): array
    {
        $sql = 'SELECT id, title, slug, description, cover_image, is_system, is_public, created_at FROM quran_playlists';
        if (!$includePrivate) {
            $sql .= ' WHERE is_public = 1';
        }
        $sql .= ' ORDER BY created_at DESC';
        $res       = $this->mysqli->query($sql);
        $playlists = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        foreach ($playlists as &$p) {
            $p['track_count'] = $this->getPlaylistTrackCount((int)$p['id']);
        }
        return $playlists;
    }

    /**
     * Get a playlist by slug.
     * @return array|null
     */
    public function getPlaylistBySlug(string $slug): ?array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT id, title, slug, description, cover_image, is_system, is_public, created_at FROM quran_playlists WHERE slug = ? LIMIT 1'
        );
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;

        $row['track_count']  = $this->getPlaylistTrackCount((int)$row['id']);
        $row['total_duration'] = $this->getPlaylistTotalDuration((int)$row['id']);
        return $row;
    }

    /**
     * Get a playlist by ID.
     * @return array|null
     */
    public function getPlaylistById(int $playlistId): ?array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT id, title, slug, description, cover_image, is_system, is_public, created_at FROM quran_playlists WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $playlistId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Create a new playlist.
     * @return int|null inserted ID
     */
    public function createPlaylist(array $data): ?int
    {
        if (empty($data['title']) || empty($data['slug'])) return null;
        $stmt = $this->mysqli->prepare(
            'INSERT INTO quran_playlists (title, slug, description, cover_image, is_public, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $title       = (string)$data['title'];
        $slug        = (string)$data['slug'];
        $description = $data['description'] ?? null;
        $cover       = $data['cover_image'] ?? null;
        $isPublic    = isset($data['is_public']) ? (int)(bool)$data['is_public'] : 1;
        $createdBy   = isset($data['created_by']) ? (int)$data['created_by'] : null;

        $stmt->bind_param(
            'ssssii',
            $title,
            $slug,
            $description,
            $cover,
            $isPublic,
            $createdBy
        );
        if ($stmt->execute()) {
            return (int)$this->mysqli->insert_id;
        }
        $stmt->close();
        return null;
    }

    /**
     * Update a playlist.
     */
    public function updatePlaylist(int $playlistId, array $data): bool
    {
        $fields = [];
        $params = [];
        $types  = '';

        foreach (['title', 'slug', 'description', 'cover_image'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = (string)$data[$field];
                $types   .= 's';
            }
        }
        if (isset($data['is_public'])) {
            $fields[] = 'is_public = ?';
            $params[] = (int)(bool)$data['is_public'];
            $types   .= 'i';
        }

        if (empty($fields)) return false;
        $fields[] = 'updated_at = NOW()';
        $params[] = $playlistId;
        $types   .= 'i';

        $stmt = $this->mysqli->prepare(
            'UPDATE quran_playlists SET ' . implode(', ', $fields) . ' WHERE id = ?'
        );
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    /**
     * Soft delete a playlist.
     */
    public function deletePlaylist(int $playlistId): bool
    {
        $stmt = $this->mysqli->prepare(
            'UPDATE quran_playlists SET is_public = 0 WHERE id = ?'
        );
        $stmt->bind_param('i', $playlistId);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    // ======================================================================
    // TRACKS
    // ======================================================================

    /**
     * Get all tracks for a playlist (ordered by track_index).
     * @return list<array>
     */
    public function getTracksByPlaylist(int $playlistId): array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT qt.id, qt.playlist_id, qt.surah_id, qt.track_index, qt.title, qt.slug,
                     qt.bangla_translation, qt.ayah_range, qt.audio_url, qt.duration_seconds,
                     qt.reciter_name, qt.is_visible, qt.created_at,
                     qs.name_arabic, qs.name_bangla, qs.name_english, qs.total_ayahs
              FROM quran_tracks qt
              LEFT JOIN quran_surahs qs ON qt.surah_id = qs.id
              WHERE qt.playlist_id = ?
              ORDER BY qt.track_index ASC, qt.id ASC'
        );
        $stmt->bind_param('i', $playlistId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Get a single track by its playlist + slug.
     * @return array|null
     */
    public function getTrackBySlug(int $playlistId, string $slug): ?array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT qt.id, qt.playlist_id, qt.surah_id, qt.track_index, qt.title, qt.slug,
                     qt.bangla_translation, qt.ayah_range, qt.audio_url, qt.duration_seconds,
                     qt.reciter_name, qt.is_visible, qt.created_at,
                     qs.name_arabic, qs.name_bangla, qs.name_english
              FROM quran_tracks qt
              LEFT JOIN quran_surahs qs ON qt.surah_id = qs.id
              WHERE qt.playlist_id = ? AND qt.slug = ?
              LIMIT 1'
        );
        $stmt->bind_param('is', $playlistId, $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Create a track record.
     * @return int|null
     */
    public function createTrack(array $data): ?int
    {
        $stmt = $this->mysqli->prepare(
            'INSERT INTO quran_tracks (playlist_id, surah_id, track_index, title, slug, ayah_range,
                                       bangla_translation, arabic_text, audio_url, duration_seconds, reciter_name,
                                       is_visible, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $playlistId = (int)($data['playlist_id'] ?? 0);
        $surahId    = isset($data['surah_id']) ? (int)$data['surah_id'] : null;
        $trackIndex = (int)($data['track_index'] ?? 0);
        $title      = (string)($data['title'] ?? '');
        $slug       = (string)($data['slug'] ?? '');
        $ayahRange  = $data['ayah_range'] ?? null;
        $bnTrans    = $data['bangla_translation'] ?? null;
        $arabic     = $data['arabic_text'] ?? null;
        $audioUrl   = (string)($data['audio_url'] ?? '');
        $duration   = isset($data['duration_seconds']) ? (int)$data['duration_seconds'] : null;
        $reciter    = $data['reciter_name'] ?? null;
        $visible    = isset($data['is_visible']) ? (int)(bool)$data['is_visible'] : 1;

        $surahBind = $surahId ?: null;
        $stmt->bind_param(
            'iiisssssssini',
            $playlistId,
            $surahBind,
            $trackIndex,
            $title,
            $slug,
            $ayahRange,
            $bnTrans,
            $arabic,
            $audioUrl,
            $duration,
            $reciter,
            $visible
        );
        if ($stmt->execute()) {
            return (int)$this->mysqli->insert_id;
        }
        $stmt->close();
        return null;
    }

    /**
     * Get the total number of visible tracks in a playlist.
     */
    private function getPlaylistTrackCount(int $playlistId): int
    {
        $stmt = $this->mysqli->prepare(
            'SELECT COUNT(*) AS cnt FROM quran_tracks WHERE playlist_id = ? AND is_visible = 1'
        );
        $stmt->bind_param('i', $playlistId);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        return $cnt;
    }

    /**
     * Get the total track duration in a playlist (seconds → formatted).
     */
    private function getPlaylistTotalDuration(int $playlistId): string
    {
        $stmt = $this->mysqli->prepare(
            'SELECT SUM(duration_seconds) AS total FROM quran_tracks WHERE playlist_id = ? AND is_visible = 1'
        );
        $stmt->bind_param('i', $playlistId);
        $stmt->execute();
        $seconds = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        if ($seconds <= 0) return '0 sec';
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
    }

    // ======================================================================
    // HELPERS
    // ======================================================================

    /**
     * Format seconds → `mm:ss`, return `--:--` when 0.
     */
    public function formatDuration(?int $seconds): string
    {
        if (!$seconds || $seconds < 1) return '--:--';
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
