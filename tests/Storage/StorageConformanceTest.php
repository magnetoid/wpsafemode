<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Message;
use WPSafeMode\Storage\Session;

/**
 * One suite, two backends. Every assertion here must hold identically for
 * SQLite and for the JSON fallback — that equivalence is the entire point
 * of the fallback (spec 7).
 */
abstract class StorageConformanceTest extends TestCase
{
    protected string $dataDir;

    abstract protected function makeConfigStore(string $dataDir): ConfigStore;

    abstract protected function makeSessionRepo(string $dataDir): SessionRepo;

    abstract protected function makeMessageLog(string $dataDir): MessageLog;

    /**
     * Backend-specific: the JSON backend keeps one directory per session and
     * must leave none behind after delete() removes an otherwise-untouched
     * session; SQLite has no per-session directory to check, so its
     * implementation is a documented no-op.
     */
    abstract protected function assertNoSessionStorageLeftBehind(string $dataDir, string $id): void;

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/wpsm-store-' . bin2hex(random_bytes(6));
        mkdir($this->dataDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dataDir);
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }

        rmdir($path);
    }

    public function testGetReturnsDefaultWhenKeyIsAbsent(): void
    {
        self::assertNull($this->makeConfigStore($this->dataDir)->get('nope'));
        self::assertSame('fb', $this->makeConfigStore($this->dataDir)->get('nope', 'fb'));
    }

    public function testSetThenGetRoundTrips(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('model', 'claude-opus-4-8');

        self::assertSame('claude-opus-4-8', $store->get('model'));
    }

    public function testSetOverwritesExistingKey(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('effort', 'high');
        $store->set('effort', 'xhigh');

        self::assertSame('xhigh', $store->get('effort'));
        self::assertCount(1, $store->all());
    }

    public function testValuesPersistAcrossInstances(): void
    {
        $this->makeConfigStore($this->dataDir)->set('model', 'claude-sonnet-5');

        self::assertSame('claude-sonnet-5', $this->makeConfigStore($this->dataDir)->get('model'));
    }

    public function testHasReflectsPresence(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        self::assertFalse($store->has('k'));

        $store->set('k', 'v');
        self::assertTrue($store->has('k'));
    }

    public function testDeleteRemovesKeyAndIsIdempotent(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('k', 'v');
        $store->delete('k');
        $store->delete('k');

        self::assertFalse($store->has('k'));
    }

    public function testAllReturnsEveryPair(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('a', '1');
        $store->set('b', '2');

        self::assertSame(['a' => '1', 'b' => '2'], $store->all());
    }

    public function testEmptyStringIsStoredAndIsNotConfusedWithAbsence(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('blank', '');

        self::assertTrue($store->has('blank'));
        self::assertSame('', $store->get('blank', 'fallback'));
    }

    public function testValuesSurviveHostileContent(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $nasty = "line1\nline2\t\"quoted\" 'single' \\slash\\ {\"json\":true} émoji-✓ \0nul";
        $store->set('nasty', $nasty);

        self::assertSame($nasty, $this->makeConfigStore($this->dataDir)->get('nasty'));
    }

    private function sampleSession(string $id = 'sesn_1', int $createdAt = 1000): Session
    {
        return new Session(
            id:        $id,
            title:     'White screen on example.com',
            blade:     Session::BLADE_RESCUE,
            status:    Session::STATUS_IDLE,
            model:     'claude-opus-4-8',
            effort:    'high',
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    public function testFindReturnsNullForUnknownSession(): void
    {
        self::assertNull($this->makeSessionRepo($this->dataDir)->find('nope'));
    }

    public function testCreateThenFindRoundTripsEveryField(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession());

        $found = $this->makeSessionRepo($this->dataDir)->find('sesn_1');

        self::assertNotNull($found);
        self::assertSame($this->sampleSession()->toRow(), $found->toRow());
    }

    public function testCreateRejectsDuplicateId(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession());

        $this->expectException(StorageException::class);
        $repo->create($this->sampleSession());
    }

    public function testUpdatePersistsChangedFields(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession());

        $changed = $this->sampleSession();
        $changed->status = Session::STATUS_AWAITING_LLM;
        $changed->inTokens = 4096;
        $changed->lockToken = 'tok_abc';
        $repo->update($changed);

        $found = $this->makeSessionRepo($this->dataDir)->find('sesn_1');

        self::assertNotNull($found);
        self::assertSame(Session::STATUS_AWAITING_LLM, $found->status);
        self::assertSame(4096, $found->inTokens);
        self::assertSame('tok_abc', $found->lockToken);
    }

    public function testUpdateRejectsUnknownSession(): void
    {
        $this->expectException(StorageException::class);
        $this->makeSessionRepo($this->dataDir)->update($this->sampleSession('ghost'));
    }

    public function testListReturnsNewestFirstAndHonoursLimit(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession('sesn_a', 1000));
        $repo->create($this->sampleSession('sesn_b', 3000));
        $repo->create($this->sampleSession('sesn_c', 2000));

        $ids = array_map(static fn (Session $s): string => $s->id, $repo->list());
        self::assertSame(['sesn_b', 'sesn_c', 'sesn_a'], $ids);

        self::assertCount(2, $repo->list(2));
    }

    public function testDeleteRemovesSessionAndIsIdempotent(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession());
        $repo->delete('sesn_1');
        $repo->delete('sesn_1');

        self::assertNull($repo->find('sesn_1'));
        $this->assertNoSessionStorageLeftBehind($this->dataDir, 'sesn_1');
    }

    public function testAppendAssignsSequentialSeqStartingAtOne(): void
    {
        $log = $this->makeMessageLog($this->dataDir);

        self::assertSame(1, $log->append('sesn_1', Message::ROLE_USER, [['type' => 'text', 'text' => 'hi']]));
        self::assertSame(2, $log->append('sesn_1', Message::ROLE_ASSISTANT, [['type' => 'text', 'text' => 'hello']]));
    }

    public function testSeqIsPerSessionNotGlobal(): void
    {
        $log = $this->makeMessageLog($this->dataDir);
        $log->append('sesn_a', Message::ROLE_USER, [['type' => 'text', 'text' => 'a']]);

        self::assertSame(1, $log->append('sesn_b', Message::ROLE_USER, [['type' => 'text', 'text' => 'b']]));
    }

    public function testAllYieldsMessagesInSeqOrderWithContentIntact(): void
    {
        $log = $this->makeMessageLog($this->dataDir);

        $toolUse = [[
            'type'  => 'tool_use',
            'id'    => 'toolu_01',
            'name'  => 'read_debug_log',
            'input' => ['lines' => 400, 'note' => "multi\nline \"quoted\""],
        ]];

        $log->append('sesn_1', Message::ROLE_USER, [['type' => 'text', 'text' => 'diagnose']]);
        $log->append('sesn_1', Message::ROLE_ASSISTANT, $toolUse, 'msg_abc');

        $messages = iterator_to_array($this->makeMessageLog($this->dataDir)->all('sesn_1'), false);

        self::assertCount(2, $messages);
        self::assertSame(1, $messages[0]->seq);
        self::assertSame(2, $messages[1]->seq);
        self::assertSame($toolUse, $messages[1]->content, 'Content blocks must round-trip verbatim');
        self::assertSame('msg_abc', $messages[1]->apiMessageId);
    }

    public function testAllIsEmptyForUnknownSession(): void
    {
        self::assertSame([], iterator_to_array($this->makeMessageLog($this->dataDir)->all('ghost'), false));
    }

    public function testCountTracksAppends(): void
    {
        $log = $this->makeMessageLog($this->dataDir);
        self::assertSame(0, $log->count('sesn_1'));

        $log->append('sesn_1', Message::ROLE_USER, [['type' => 'text', 'text' => 'x']]);
        self::assertSame(1, $log->count('sesn_1'));
    }
}
