<?php

namespace Indibit\Tests;

use PHPUnit\Framework\TestCase;
use Indibit\ArrayFacade as A;

class ArrayFacadeTest extends TestCase
{

    public function testSum(): void
    {
        $this->assertEquals(6, A::of([1, 2, 3])->sum()->get());
        $this->assertEquals(6.3, A::of([1.1, 2.1, 3.1])->sum()->get());
    }

    public function testToGraph(): void
    {
        $g1 = A::of(
            [
                ['id' => 1, 'parent' => null],
                ['id' => 2, 'parent' => ['id' => 3]],
                ['id' => 3, 'parent' => null],          // zweite Wurzel
                ['id' => 4, 'parent' => ['id' => 2]],   // zweite Ebene
                ['id' => 5, 'parent' => ['id' => 1]]
            ]
        )->toGraph('id', 'parent', 'children');
        /*
         * [
         *   { id: 1, children: [ { id: 5 } ],
         *   {
         *     id: 3,
         *     children: [
         *       { id: 2, children: [ { id: 4 } ] }
         *     ]
         * ]
         */
        $this->assertEquals(5, $g1[0]['children'][0]['id']);
        $this->assertEquals(4 ,$g1[1]['children'][0]['children'][0]['id']);
    }

    public function testIsArray(): void
    {
        /*
         * is_array liefert false für ArrayFacade
         */
        $this->assertFalse(is_array(A::ofEmpty()));
    }

    public function testEmpty(): void
    {
        /*
         * empty() liefert immer false für ArrayFacade
         */
        $this->assertFalse(empty(A::ofEmpty()));
    }

    public function testSortBy(): void
    {
        /*
         * Eigenschaft
         */
        $a = A::of([['id' => 2], ['id' => 1]]);
        $b = $a->sortBy('id');
        $this->assertEquals(2, $a[0]['id']);
        $this->assertEquals(1, $b[0]['id']);
        /*
         * Ausdruck
         */
        $a = A::of([['id' => 2, 'o' => ['x' => 'b']], ['id' => 1, 'o' => ['x' => 'a']]]);
        $b = $a->sortBy('o.x');
        $this->assertEquals(2, $a[0]['id']);
        $this->assertEquals(1, $b[0]['id']);
    }

    public function testMapValues(): void
    {
        $a = A::of(['a' => 1, 'b' => 2]);
        $r = $a->mapValues(
            function ($v, $k) {
                $this->assertTrue($v === 1 || $v === 2);
                $this->assertTrue($k === 'a' || $k === 'b');
                return $v * $v;
            }
        );
        $this->assertEquals(1, $r['a']);
        $this->assertEquals(4, $r['b']);
    }

    public function testEquals(): void
    {
        self::assertTrue(A::of([1, 2, 3])->equals(A::of([1, 2, 3])));
        self::assertTrue(A::of([['a' => 1], ['b' => 2]])->equals((A::of([['a' => 1], ['b' => 2]]))));
        self::assertFalse(A::of([1, 2, 3])->equals(A::of(['1', 2, 3])));
    }

    public function testPaths(): void
    {
        self::assertTrue(
            A::of([['a' => 1], ['a' => 2]])
                ->map('a')
                ->equals(A::of([1, 2])));
        self::assertTrue(
            A::of([['a' => ['b' => 1]], ['a' => ['b' => 2]]])
                ->map('a.b')
                ->equals(A::of([1, 2])));
        self::assertTrue(
            A::of([['a' => ['b' => ['c' => 1]]], ['a' => ['b' => ['c' => 2]]]])
                ->map('a.b.c')
                ->equals(A::of([1, 2])));
    }

    public function testGroupByPath(): void
    {
        self::assertEquals(2, A::of([
            ['parent' => ['some' => true]],
            ['parent' => ['some' => true]],
            ['parent' => ['some' => false]]
        ])->groupBy('parent.some')->count());
    }

    public function testPhpCount(): void
    {
        self::assertEquals(3, count(A::of([1, 2, 3])));
    }

    public function testHead(): void
    {
        self::assertEquals(4711, A::of([4711, 4712])->head()->get());
        self::expectError();
        A::of(['abc' => 4711])->head()->get();
    }

    public function testMinBy(): void
    {
        $o = A::of([
            ['id' => 1, 'x' => 4711],
            ['id' => 2, 'x' => 4710],
            ['id' => 3, 'x' => 4712],
            ['id' => 4, 'x' => 4710]
        ])->minBy('x')->get();
        self::assertTrue($o['id'] === 2 || $o['id'] === 4);
    }

    public function testIsset(): void
    {
        $a = A::of(['x' => 1, 'y' => null]);
        self::assertTrue(isset($a['x']));
        self::assertFalse(isset($a['y']));
        self::assertFalse(isset($a['z']));
    }

    public function testReverse(): void
    {
        $a = A::of([
            ['id' => 0],
            ['id' => 1],
            ['id' => 2]
        ])->reverse();
        self::assertEquals(2, $a[0]['id']);
        self::assertEquals(1, $a[1]['id']);
        self::assertEquals(0, $a[2]['id']);
    }

}