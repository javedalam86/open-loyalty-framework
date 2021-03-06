<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Component\Customer\Domain\Event;

use OpenLoyalty\Component\Customer\Domain\CustomerId;
use OpenLoyalty\Component\Customer\Domain\LevelId;

/**
 * Class CustomerWasMovedToLevel.
 */
class CustomerWasMovedToLevel extends CustomerEvent
{
    /**
     * @var LevelId
     */
    protected $levelId;

    /**
     * @var \DateTime
     */
    protected $updateAt;

    /**
     * @var bool
     */
    protected $manually = false;

    /**
     * @var bool
     */
    protected $removeLevelManually = false;

    /**
     * CustomerWasMovedToLevel constructor.
     *
     * @param CustomerId   $customerId
     * @param LevelId|null $levelId
     * @param bool         $manually
     * @param bool         $removeLevelManually
     */
    public function __construct(
        CustomerId $customerId,
        LevelId $levelId = null,
        $manually = false,
        bool $removeLevelManually = false
    ) {
        parent::__construct($customerId);
        $this->levelId = $levelId;
        $this->updateAt = new \DateTime();
        $this->updateAt->setTimestamp(time());
        $this->manually = $manually;
        $this->removeLevelManually = $removeLevelManually;
    }

    public function serialize(): array
    {
        return array_merge(parent::serialize(), [
           'levelId' => $this->levelId ? $this->levelId->__toString() : null,
            'updatedAt' => $this->updateAt ? $this->updateAt->getTimestamp() : null,
            'manually' => $this->manually,
            'removeLevelManually' => $this->removeLevelManually,
        ]);
    }

    /**
     * @param array $data
     *
     * @return mixed The object instance
     */
    public static function deserialize(array $data)
    {
        $event = new self(
            new CustomerId($data['customerId']),
            $data['levelId'] ? new LevelId($data['levelId']) : null,
            $data['manually'],
            isset($data['removeLevelManually']) ? $data['removeLevelManually'] : false
        );
        if (isset($data['updatedAt'])) {
            $date = new \DateTime();
            $date->setTimestamp($data['updatedAt']);
            $event->setUpdateAt($date);
        }

        return $event;
    }

    /**
     * @return LevelId
     */
    public function getLevelId()
    {
        return $this->levelId;
    }

    /**
     * @return \DateTime
     */
    public function getUpdateAt()
    {
        return $this->updateAt;
    }

    /**
     * @param \DateTime $updateAt
     */
    public function setUpdateAt($updateAt)
    {
        $this->updateAt = $updateAt;
    }

    /**
     * @return bool
     */
    public function isManually()
    {
        return $this->manually;
    }

    /**
     * @return bool
     */
    public function isRemoveLevelManually(): bool
    {
        return $this->removeLevelManually;
    }

    /**
     * @param bool $removeLevelManually
     */
    public function setRemoveLevelManually(bool $removeLevelManually)
    {
        $this->removeLevelManually = $removeLevelManually;
    }
}
