<?php

namespace Kirby\Cms;

use Exception;
use Kirby\Cache\Cache;
use Kirby\Toolkit\Str;

/**
 *
 * Binding::for(model)->toUuid();
 * Binding::for($uuid)->toModel();
 *
 */

class Binding
{

	protected static $cache;
	protected $id;
	protected $model;
	protected $type;

	protected static $uuids = [];

	public function __construct(
		string|null $uuid = null,
	 	ModelWithContent|null $model = null
	)
	{
		$this->model = $model;
		$this->id    = Str::after($uuid, '://');

		$this->type   = Str::before($uuid, '://');
		$this->type ??= match ($model::class) {
			'Kirby\\Cms\\Site' => 'site',
			'Kirby\\Cms\\Page' => 'page',
			'Kirby\\Cms\\File' => 'file',
			'Kirby\\Cms\\User' => 'user',
		};
	}

	/**
	 * Get cache instance for lookup cache
	 *
	 * @return \Kirby\Cache\Cache
	 */
	public function cache(): Cache
	{
		return App::instance()->cache('uuid');
	}

	/**
	 * Lookup uuid in cache and resolve to site/page/file/user
	 *
	 * @return ModelWithContent|null
	 */
	protected function findFromCache(): ModelWithContent|null
	{
		$id = $this->cache()->get($this->id);

		return match($this->type) {
			'page' => App::instance()->page($id),
			'file' => App::instance()->file($id)
		};
	}

	/**
	 * Lookup site/page/file/user by traversing through index
	 *
	 * @return ModelWithContent|null
	 */
	protected function findFromIndex(): ModelWithContent|null
	{
		$collection = match($this->type) {
			'page' => App::instance()->site()->index(true),
			'file' => App::instance()->site()->index(true)->files()
		};

		return $collection->filterBy('uuid', $this->id)->first();
	}


	/**
	 * Create binding instance for UUID or site/page/file/user
	 *
	 * @param string|ModelWithContent $base
	 * @return static
	 */
	public static function for(string|ModelWithContent $base): static
	{
		if (is_string($base) === true) {
			return new static(uuid: $base);
		}

		return new static(model: $base);
	}

	/**
	 * Generate a new UUID
	 *
	 * @return string
	 */
	protected function generate(): string
	{
		do {
			$temp = Str::uuid();

			if (
				// this is horrible performance wise
				$this->findFromIndex($temp, 'page') === null &&
				$this->findFromIndex($temp, 'file') === null
			) {
				return $temp;
			}
		} while (true);
	}

	/**
	 * Resolve binding to site/page/file/user
	 *
	 * @return ModelWithContent
	 */
	public function toModel(): ModelWithContent
	{
		if (isset(static::$uuids[$this->scheme()]) === true) {
			return static::$uuids[$this->scheme()];
		}

		$this->model ??= match($this->type) {
			'site'  => App::instance()->site(),
			'user'  => App::instance()->user($this->id),
			default => $this->findFromCache() ?? $this->findFromIndex()
		};

		return static::$uuids[$this->scheme()] = $this->model;
	}

	/**
	 * Resolve binding to UUID
	 *
	 * @return string
	 */
	public function toUuid(): string
	{
		$this->id ??= match($this->type) {
			'site'  => '',
			'user'  => $this->model->id(),
			default => $this->model->content()->get('uuid') ?? static::generate()
		};

		// TODO: should write to content file if needed

		return $this->scheme();
	}

	public function scheme(): string
	{
		return $this->type . '://' . $this->id;
	}
}
