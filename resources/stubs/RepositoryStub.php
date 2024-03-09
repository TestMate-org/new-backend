<?php declare (strict_types = 1);

namespace DummyNamespace;

use TestMate\Repositories\AbstractRepository;

/**
 * DummyClass repository
 * @author TestMate <dev@testmate.org>
 */
final class DummyClass extends AbstractRepository
{
    /**
     * Table of repository
     * @var string $table
     */
    protected string $table = '';
}
