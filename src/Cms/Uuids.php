<?php

namespace Kirby\Cms;

use Exception;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Cms\User;
use Kirby\Toolkit\Str;

class Uuids
{

	public function __construct(protected App $kirby)
	{}

	public function generate(): string
	{
		do {
			$id = Str::uuid();

			if (
				$this->find($id, 'page') === null &&
				$this->find($id, 'file') === null
			) {
				return $id;
			}
		} while (true);
	}

	public function find(string $uuid, string $scheme): string
	{
		return $this->findFromCache($uuid) ??
			   $this->findFromIndex($uuid, $scheme);
	}

	protected function findFromCache(string $uuid): string|null
	{
		return $this->cache->get($uuid);

	}

	protected function findFromIndex(string $uuid, string $scheme): string|null
	{
		$collection = match ($scheme) {
			'page' => $this->kirby->site()->index(true),
			'file' => $this->kirby->site()->index(true)->files()
		};

		$matches = $collection->filterBy('uuid', $uuid);

		if ($matches->count() > 1) {
			throw new Exception('uuid not unique!');
		}

		if ($matches->count() === 0) {
			return null;
		}

		return $matches->first()->id();
	}

	public function toModel(string $uuid): Site|Page|File|User|null
	{
		$scheme = Str::before($uuid, '://');
		$uuid   = Str::after($uuid, '://');

		if ($scheme === 'site') {
			return $this->kirby->site();
		}

		if ($scheme === 'user') {
			return $this->kirby->user($uuid);
		}

		$id = $this->find($uuid, $scheme);

		if ($scheme === 'page') {
			return $this->kirby->page($id);
		}

		if ($scheme === 'file') {
			return $this->kirby->file($id);
		}

		throw new Exception('uuid with unsupported scheme: ' . $uuid);
	}

}
