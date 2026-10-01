<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ProductAPI\Exception;

/**
 * The request cannot be answered and the caller may be told why: the message is safe to send back.
 */
final class RejectedRequestException extends \DomainException
{
}
