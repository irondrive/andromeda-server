<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

/** An object used to bridge together N other object classes (many to many relationship) */
abstract class JoinObject extends BaseObject
{
    /**
     * @param array<string, BaseObject> $matchobjs
     * @return static
     */
    protected static function TryLoadJoinObject(ObjectDatabase $database, array $matchobjs) : ?self
    {
        $q = new QueryBuilder();

        foreach ($matchobjs as $matchprop=>$matchobj)
            $q->Where($q->Equals($matchprop, $matchobj->ID()));

        return $database->TryLoadUniqueByQuery(static::class, $q);
    }

    /** 
     * @param class-string<BaseObject> $destclass
     * @param array<string, BaseObject> $matchobjs
     */
    private static function GetJoinQuery(ObjectDatabase $database, string $destclass, string $destprop, array $matchobjs) : QueryBuilder
    {
        $q = new QueryBuilder();

        foreach ($matchobjs as $matchprop=>$matchobj)
            $q->Where($q->Equals($matchprop, $matchobj->ID()));

        return $q->Join($database, static::class, $destprop, $destclass, 'id');
    }

    /** 
     * @template T of BaseObject
     * @param class-string<T> $destclass
     * @param array<string, BaseObject> $matchobjs
     * @return array<string, T>
     */
    protected static function LoadFromJoin(ObjectDatabase $database, string $destclass, string $destprop, array $matchobjs) : array
    {
        $q = self::GetJoinQuery($database, $destclass, $destprop, $matchobjs);
        return $database->LoadObjectsByQuery($destclass, $q);
    }

    /** 
     * @template T of BaseObject
     * @param class-string<T> $destclass
     * @param array<string, BaseObject> $matchobjs
     * @return int
     */
    protected static function CountFromJoin(ObjectDatabase $database, string $destclass, string $destprop, array $matchobjs) : int
    {
        $q = self::GetJoinQuery($database, $destclass, $destprop, $matchobjs);
        return $database->CountObjectsByQuery($destclass, $q);
    }

    /** 
     * @template T of BaseObject
     * @param class-string<T> $destclass
     * @param array<string, BaseObject> $matchobjs
     * @return int
     */
    protected static function DeleteFromJoin(ObjectDatabase $database, string $destclass, string $destprop, array $matchobjs) : int
    {
        $q = self::GetJoinQuery($database, $destclass, $destprop, $matchobjs);
        return $database->DeleteObjectsByQuery($destclass, $q);
    }

    /** @return static */
    protected static function BaseCreate(ObjectDatabase $database) : self
    {
        return $database->CreateObject(static::class);
    }

    protected function BaseDelete() : void
    {
        $this->database->DeleteObject($this);
    }

    // TODO !! better comments for all functions

    // TODO RAY !! implement caching layer - arr[left class][right id]
    // only get cache on load - add to it only when creating/deleting... no merge semantics

}
