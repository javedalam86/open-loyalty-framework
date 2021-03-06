<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Bundle\PointsBundle\Service;

use Broadway\CommandHandling\CommandBus;
use OpenLoyalty\Bundle\SettingsBundle\Service\SettingsManager;
use OpenLoyalty\Component\Account\Domain\Command\ExpirePointsTransfer;
use OpenLoyalty\Component\Account\Domain\ReadModel\PointsTransferDetails;
use OpenLoyalty\Component\Account\Domain\ReadModel\PointsTransferDetailsRepository;

/**
 * Class PointsTransfersManager.
 */
class PointsTransfersManager
{
    /**
     * @var CommandBus
     */
    protected $commandBus;

    /**
     * @var PointsTransferDetailsRepository
     */
    protected $pointsTransferDetailsRepository;

    /**
     * @var SettingsManager
     */
    protected $settingsManager;

    /**
     * PointsTransfersManager constructor.
     *
     * @param CommandBus                      $commandBus
     * @param PointsTransferDetailsRepository $pointsTransferDetailsRepository
     * @param SettingsManager                 $settingsManager
     */
    public function __construct(
        CommandBus $commandBus,
        PointsTransferDetailsRepository $pointsTransferDetailsRepository,
        SettingsManager $settingsManager
    ) {
        $this->commandBus = $commandBus;
        $this->pointsTransferDetailsRepository = $pointsTransferDetailsRepository;
        $this->settingsManager = $settingsManager;
    }

    public function expireTransfers()
    {
        $allTime = $this->settingsManager->getSettingByKey('allTimeActive');
        if (null !== $allTime && $allTime->getValue()) {
            return [];
        }
        $days = $this->settingsManager->getSettingByKey('pointsDaysActive');
        if (!$days) {
            $days = 60;
        } else {
            $days = $days->getValue();
        }
        $date = new \DateTime();
        $date->setTime(0, 0, 0);
        $date->modify('-'.$days.' days');
        $timestamp = $date->getTimestamp();
        $transfers = $this->pointsTransferDetailsRepository->findAllActiveAddingTransfersCreatedAfter($timestamp);

        /** @var PointsTransferDetails $transfer */
        foreach ($transfers as $transfer) {
            $this->commandBus->dispatch(new ExpirePointsTransfer(
                $transfer->getAccountId(),
                $transfer->getPointsTransferId()
            ));
        }

        return $transfers;
    }
}
