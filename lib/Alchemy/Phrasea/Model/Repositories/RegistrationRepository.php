<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2014 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Model\Repositories;

use Doctrine\ORM\EntityRepository;
use Alchemy\Phrasea\Model\Entities\User;

/**
 * RegistrationRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class RegistrationRepository extends EntityRepository
{
    /**
     * Displays registrations for user on provided collection.
     *
     * @param User          $user
     * @param \collection[] $collections
     *
     * @return array
     */
    public function getUserRegistrations(User $user, array $collections)
    {
        $qb = $this->createQueryBuilder('d');
        $qb->where($qb->expr()->eq('d.user', ':user'));
        $qb->setParameter(':user', $user->getId());

        if (count($collections) > 0) {
            $qb->andWhere('d.baseId IN (:bases)');
            $qb->setParameter(':bases', array_map(function ($collection) {
                return $collection->get_base_id();
            }, $collections));
        }

        $qb->orderBy('d.created', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Gets registration registrations for a user.
     *
     * @param User $user
     *
     * @return array
     */
    public function getRegistrationsSummaryForUser(User $user)
    {
        $data = [];
        $rsm = $this->createResultSetMappingBuilder('d');
        $rsm->addScalarResult('sbas_id','sbas_id');
        $rsm->addScalarResult('bas_id','bas_id');
        $rsm->addScalarResult('dbname','dbname');
        $rsm->addScalarResult('time_limited', 'time_limited');
        $rsm->addScalarResult('limited_from', 'limited_from');
        $rsm->addScalarResult('limited_to', 'limited_to');
        $rsm->addScalarResult('actif', 'actif');

        $sql = "
        SELECT dbname, sbas.sbas_id, time_limited,
               UNIX_TIMESTAMP( limited_from ) AS limited_from,
               UNIX_TIMESTAMP( limited_to ) AS limited_to,
               bas.server_coll_id, usr.usr_id, basusr.actif,
               bas.base_id AS bas_id , " . $rsm->generateSelectClause(['d' => 'd',]) . "
        FROM (usr, bas, sbas)
          LEFT JOIN basusr ON ( usr.usr_id = basusr.usr_id AND bas.base_id = basusr.base_id )
          LEFT JOIN Registrations d ON ( d.user_id = usr.usr_id AND bas.base_id = d.base_id )
        WHERE bas.active = 1 AND bas.sbas_id = sbas.sbas_id
        AND usr.usr_id = ?
        AND model_of = 0";

        $query = $this->_em->createNativeQuery($sql, $rsm);
        $query->setParameter(1, $user->getId());

        foreach ($query->getResult() as $row) {
            $registrationEntity = $row[0];

            $data[$row['sbas_id']][$row['bas_id']] = [
                'base-id' => $row['bas_id'],
                'db-name' => $row['dbname'],
                'active' => (Boolean) $row['actif'],
                'time-limited' => (Boolean) $row['time_limited'],
                'in-time' => $row['time_limited'] && ! ($row['limited_from'] >= time() && $row['limited_to'] <= time()),
                'registration' => $registrationEntity
            ];
        }

        return $data;
    }
}
