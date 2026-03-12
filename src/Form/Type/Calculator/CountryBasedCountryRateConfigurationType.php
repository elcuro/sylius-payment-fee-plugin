<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Form\Type\Calculator;

use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CountryBasedCountryRateConfigurationType extends AbstractType
{
    /**
     * @param RepositoryInterface<CountryInterface> $countryRepository
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     */
    public function __construct(
        private readonly RepositoryInterface $countryRepository,
        private readonly RepositoryInterface $currencyRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();

            $currencyChoices = $this->getCurrencyChoices();

            /** @var CountryInterface[] $countries */
            $countries = $this->countryRepository->findBy(['enabled' => true]);

            foreach ($countries as $country) {
                $countryCode = $country->getCode();
                if (null === $countryCode) {
                    continue;
                }

                $form->add($countryCode, CountryRateConfigurationType::class, [
                    'label' => $country->getName(),
                    'currency_choices' => $currencyChoices,
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'elcuro_country_based_payment_calculator_country_rate';
    }

    /** @return array<string, string> */
    private function getCurrencyChoices(): array
    {
        $choices = [];

        /** @var CurrencyInterface[] $currencies */
        $currencies = $this->currencyRepository->findAll();

        foreach ($currencies as $currency) {
            $code = $currency->getCode();
            if (null !== $code) {
                $choices[$code] = $code;
            }
        }

        return $choices;
    }
}
