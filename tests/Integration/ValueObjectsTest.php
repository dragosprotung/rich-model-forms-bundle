<?php

/*
 * This file is part of the RichModelFormsBundle package.
 *
 * (c) Christian Flothmann <christian.flothmann@sensiolabs.de>
 * (c) Christopher Hertel <christopher.hertel@sensiolabs.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SensioLabs\RichModelForms\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SensioLabs\RichModelForms\ExceptionHandling\FormExceptionHandler;
use SensioLabs\RichModelForms\Extension\RichModelFormsTypeExtension;
use SensioLabs\RichModelForms\Tests\ExceptionHandlerRegistryTrait;
use SensioLabs\RichModelForms\Tests\Fixtures\Form\GrossPriceType;
use SensioLabs\RichModelForms\Tests\Fixtures\Form\PriceType;
use SensioLabs\RichModelForms\Tests\Fixtures\Model\GrossPrice;
use SensioLabs\RichModelForms\Tests\Fixtures\Model\Price;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ValueObjectsTest extends TestCase
{
    use ExceptionHandlerRegistryTrait;

    public function testNonCompoundRootFormDoesNotRequirePropertyPathToBeSetIfPropertyPathCanBeDerivedFromFormName(): void
    {
        $form = $this->createNamedForm('amount', PriceType::class, new Price(500), [
            'factory' => Price::class,
            'immutable' => true,
        ]);

        $this->assertSame('500', $form->getViewData());
    }

    public function testTransformNonCompoundRootFormToViewData(): void
    {
        $form = $this->createForm(PriceType::class, new Price(500), [
            'factory' => Price::class,
            'immutable' => true,
            'property_path' => 'amount',
        ]);

        $this->assertSame('500', $form->getViewData());
    }

    public function testTransformCompoundRootFormToViewData(): void
    {
        $form = $this->createForm(GrossPriceType::class, new GrossPrice(500, 19), [
            'factory' => GrossPrice::class,
            'immutable' => true,
        ]);

        $this->assertSame('500', $form->get('amount')->getViewData());
        $this->assertSame('19', $form->get('taxRate')->getViewData());
    }

    public function testTransformSkipsEmbeddedButtons(): void
    {
        $form = $this->createForm(GrossPriceType::class, new GrossPrice(500, 19), [
            'factory' => GrossPrice::class,
            'immutable' => true,
            'include_button' => true,
        ]);

        $this->assertSame('500', $form->get('amount')->getViewData());
        $this->assertSame('19', $form->get('taxRate')->getViewData());
    }

    public function testTransformationFailedExceptionIsThrownWhenTheClassConstructorIsNotPublic(): void
    {
        $form = $this->createForm(GrossPriceType::class, new GrossPrice(500, 19), [
            'factory' => PrivateConstructorGrossType::class,
            'immutable' => true,
        ]);
        $form->submit([
            'amount' => '650',
            'taxRate' => '7',
        ]);

        $this->assertFalse($form->isSynchronized());
        $this->assertInstanceOf(TransformationFailedException::class, $form->getTransformationFailure());
    }

    public function testReverseTransformNonCompoundRootFormToNormDataUsingConstructor(): void
    {
        $form = $this->createForm(PriceType::class, new Price(500), [
            'factory' => Price::class,
            'immutable' => true,
            'property_path' => 'amount',
        ]);
        $form->submit('650');

        $price = $form->getData();

        $this->assertSame(650, $price->amount());
    }

    public function testReverseTransformNonCompoundRootFormToNormDataUsingFactoryMethod(): void
    {
        $form = $this->createForm(PriceType::class, new Price(500), [
            'factory' => [Price::class, 'fromAmount'],
            'immutable' => true,
            'property_path' => 'amount',
        ]);
        $form->submit('650');

        $price = $form->getData();

        $this->assertSame(650, $price->amount());
    }

    public function testReverseTransformNonCompoundRootFormToNormDataUsingClosure(): void
    {
        $form = $this->createForm(PriceType::class, new Price(500), [
            'factory' => function (int $amount): Price {
                return Price::fromAmount($amount);
            },
            'immutable' => true,
            'property_path' => 'amount',
        ]);
        $form->submit('650');

        $price = $form->getData();

        $this->assertSame(650, $price->amount());
    }

    public function testReverseTransformCompoundRootFormToNormDataUsingConstructor(): void
    {
        $form = $this->createForm(GrossPriceType::class, new GrossPrice(500, 19), [
            'factory' => GrossPrice::class,
            'immutable' => true,
        ]);
        $form->submit([
            'amount' => '650',
            'taxRate' => '7',
        ]);

        $grossPrice = $form->getData();

        $this->assertInstanceOf(GrossPrice::class, $grossPrice);
        $this->assertSame(650, $grossPrice->amount());
        $this->assertSame(7, $grossPrice->taxRate());
    }

    public function testReverseTransformCompoundRootFormToNormDataUsingFactoryMethod(): void
    {
        $form = $this->createForm(GrossPriceType::class, new GrossPrice(500, 19), [
            'factory' => [GrossPrice::class, 'withAmountAndTaxRate'],
            'immutable' => true,
        ]);
        $form->submit([
            'amount' => '650',
            'taxRate' => '7',
        ]);

        $grossPrice = $form->getData();

        $this->assertInstanceOf(GrossPrice::class, $grossPrice);
        $this->assertSame(650, $grossPrice->amount());
        $this->assertSame(7, $grossPrice->taxRate());
    }

    public function testReverseTransformCompoundRootFormToNormDataUsingClosure(): void
    {
        $form = $this->createForm(GrossPriceType::class, new GrossPrice(500, 19), [
            'factory' => function (array $values): GrossPrice {
                return GrossPrice::withAmountAndTaxRate($values['amount'], $values['taxRate']);
            },
            'immutable' => true,
        ]);
        $form->submit([
            'amount' => '650',
            'taxRate' => '7',
        ]);

        $grossPrice = $form->getData();

        $this->assertInstanceOf(GrossPrice::class, $grossPrice);
        $this->assertSame(650, $grossPrice->amount());
        $this->assertSame(7, $grossPrice->taxRate());
    }

    private function createForm(string $type, $data = null, array $options = []): FormInterface
    {
        return $this->createFormFactory()->createBuilder($type, $data, $options)->getForm();
    }

    private function createNamedForm(string $name, string $type, $data = null, array $options = []): FormInterface
    {
        return $this->createFormFactory()->createNamedBuilder($name, $type, $data, $options)->getForm();
    }

    private function createFormFactory(): FormFactoryInterface
    {
        $exceptionHandlerRegistry = $this->createExceptionHandlerRegistry();

        return (new FormFactoryBuilder())
            ->addTypeExtension(new RichModelFormsTypeExtension(PropertyAccess::createPropertyAccessor(), $exceptionHandlerRegistry, new FormExceptionHandler($exceptionHandlerRegistry)))
            ->getFormFactory();
    }
}

class PrivateConstructorGrossType
{
    private function __construct(int $amount, int $taxRate)
    {
    }
}
