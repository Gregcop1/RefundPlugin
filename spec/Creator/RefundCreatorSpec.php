<?php

declare(strict_types=1);

namespace spec\Sylius\RefundPlugin\Creator;

use Doctrine\Common\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use Prooph\ServiceBus\EventBus;
use Prophecy\Argument;
use Sylius\RefundPlugin\Checker\UnitRefundingAvailabilityCheckerInterface;
use Sylius\RefundPlugin\Creator\RefundCreatorInterface;
use Sylius\RefundPlugin\Entity\RefundInterface;
use Sylius\RefundPlugin\Event\UnitRefunded;
use Sylius\RefundPlugin\Exception\UnitAlreadyRefundedException;
use Sylius\RefundPlugin\Factory\RefundFactoryInterface;

final class RefundCreatorSpec extends ObjectBehavior
{
    function let(
        RefundFactoryInterface $refundFactory,
        UnitRefundingAvailabilityCheckerInterface $unitRefundingAvailabilityChecker,
        ObjectManager $refundEntityManager,
        EventBus $eventBus
    ): void {
        $this->beConstructedWith(
            $refundFactory,
            $unitRefundingAvailabilityChecker,
            $refundEntityManager,
            $eventBus
        );
    }

    function it_implements_refund_creator_interface(): void
    {
        $this->shouldImplement(RefundCreatorInterface::class);
    }

    function it_creates_refund_with_given_data_and_save_it_in_database(
        RefundFactoryInterface $refundFactory,
        UnitRefundingAvailabilityCheckerInterface $unitRefundingAvailabilityChecker,
        ObjectManager $refundEntityManager,
        EventBus $eventBus,
        RefundInterface $refund
    ): void {
        $unitRefundingAvailabilityChecker->__invoke(1)->willReturn(true);

        $refundFactory->createWithData('000222', 1, 1000)->willReturn($refund);

        $refundEntityManager->persist($refund)->shouldBeCalled();
        $refundEntityManager->flush()->shouldBeCalled();

        $eventBus->dispatch(Argument::that(function (UnitRefunded $event): bool {
            return
                $event->orderNumber() === '000222' &&
                $event->unitId() === 1 &&
                $event->amount() === 1000
            ;
        }))->shouldBeCalled();

        $this('000222', 1, 1000);
    }

    function it_throws_exception_if_unit_has_already_been_refunded(
        UnitRefundingAvailabilityCheckerInterface $unitRefundingAvailabilityChecker
    ): void {
        $unitRefundingAvailabilityChecker->__invoke(1)->willReturn(false);

        $this
            ->shouldThrow(UnitAlreadyRefundedException::class)
            ->during('__invoke', ['000222', 1, 1000])
        ;
    }
}