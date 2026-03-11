<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Form\Extension;

use Elcuro\SyliusPaymentFeePlugin\Calculator\CalculatorInterface;
use Elcuro\SyliusPaymentFeePlugin\Form\Type\CalculatorChoiceType;
use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use Sylius\Bundle\PaymentBundle\Form\Type\PaymentMethodType as SyliusPaymentMethodType;
use Sylius\Bundle\ResourceBundle\Form\Registry\FormTypeRegistryInterface;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

final class PaymentMethodTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private ServiceRegistryInterface $calculatorRegistry,
        private FormTypeRegistryInterface $formTypeRegistry,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [SyliusPaymentMethodType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('taxCategory', TaxCategoryChoiceType::class, [
                'required' => false,
                'label' => 'sylius.form.shipping_method.tax_category',
            ])
            ->add('calculator', CalculatorChoiceType::class, [
                'label' => 'elcuro_sylius_payment_fee.form.payment_method.calculator',
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                /** @var PaymentMethodWithFeeInterface|null $method */
                $method = $event->getData();

                if (!$method instanceof PaymentMethodWithFeeInterface || $method->getId() === null) {
                    return;
                }

                if ($method->getCalculator() !== null) {
                    $this->addConfigurationField($event->getForm(), $method->getCalculator());
                }
            })
            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
                $data = $event->getData();

                if (!\is_array($data) || empty($data) || !array_key_exists('calculator', $data)) {
                    return;
                }

                $this->addConfigurationField($event->getForm(), $data['calculator']);
            })
        ;

        $prototypes = [];
        foreach ($this->calculatorRegistry->all() as $name => $calculator) {
            \assert($calculator instanceof CalculatorInterface);
            $calculatorType = $calculator->getType();

            if (!$this->formTypeRegistry->has($calculatorType, 'default')) {
                continue;
            }

            /** @var class-string<\Symfony\Component\Form\FormTypeInterface> $formType */
            $formType = $this->formTypeRegistry->get($calculatorType, 'default');
            $form = $builder->create('calculatorConfiguration', $formType);
            $prototypes['calculators'][$name] = $form->getForm();
        }

        $builder->setAttribute('prototypes', $prototypes);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['prototypes'] = [];

        /** @var array<string, array<string, FormInterface>> $allPrototypes */
        $allPrototypes = $form->getConfig()->getAttribute('prototypes');

        foreach ($allPrototypes as $group => $prototypes) {
            foreach ($prototypes as $type => $prototype) {
                $view->vars['prototypes'][$group . '_' . $type] = $prototype->createView($view);
            }
        }
    }

    private function addConfigurationField(FormInterface $form, string $calculatorName): void
    {
        /** @var CalculatorInterface $calculator */
        $calculator = $this->calculatorRegistry->get($calculatorName);
        $calculatorType = $calculator->getType();

        if (!$this->formTypeRegistry->has($calculatorType, 'default')) {
            return;
        }

        $form->add('calculatorConfiguration', $this->formTypeRegistry->get($calculatorType, 'default'));
    }
}
