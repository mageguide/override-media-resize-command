<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace MageGuide\OverrideMediaStorage\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\MediaStorage\Service\ImageResize;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Magento\Framework\ObjectManagerInterface;

class ImagesResizeCommandOverride extends \Symfony\Component\Console\Command\Command
{
    /**
     * Input argument product ids
     */
    const INPUT_KEY_PRODUCT_IDS = 'products';

    /**
     * @var ImageResize
     */
    private $resize;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param State $appState
     * @param ImageResize $resize
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        State $appState,
        ImageResize $resize,
        ObjectManagerInterface $objectManager
    ) {
        parent::__construct();
        $this->resize = $resize;
        $this->appState = $appState;
        $this->objectManager = $objectManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('catalog:images:resize')
             ->setDescription('Creates resized product images');

        $this->addArgument(
            self::INPUT_KEY_PRODUCT_IDS,
            InputArgument::IS_ARRAY,
            'Space-separated list of product ids or omit to apply to all products.'
        );
    }

    /**
     * Get requested product ids
     *
     * @param InputInterface $input
     * @return array
     */
    protected function getRequestedProductIds(InputInterface $input)
    {
        $requestedProductIds = [];
        if ($input->getArgument(self::INPUT_KEY_PRODUCT_IDS)) {
            $requestedProductIds = $input->getArgument(self::INPUT_KEY_PRODUCT_IDS);
            $requestedProductIds = array_filter(array_map('trim', $requestedProductIds), 'strlen');
        }
        return $requestedProductIds;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $requestedProductIds = $this->getRequestedProductIds($input);
            if (empty($requestedProductIds)) {
                $output->writeln("<info>No product ids given. All catalog images will be generated. Have parience...</info>");
                // $this->appState->setAreaCode(Area::AREA_GLOBAL);
                // $generator = $this->resize->resizeFromThemes();
            } else {
                $requestedProductIdsString = join(",", $requestedProductIds);
                $output->writeln("<info>Start generating images for product(s): {$requestedProductIdsString}</info>");
                $this->appState->setAreaCode(Area::AREA_GLOBAL);
                $generator = $this->resize->resizeFromThemesProductIds($requestedProductIds);
            }

            /** @var ProgressBar $progress */
            $progress = $this->objectManager->create(ProgressBar::class, [
                'output' => $output,
                'max' => $generator->current()
            ]);
            $progress->setFormat(
                "%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s% \t| <info>%message%</info>"
            );

            if ($output->getVerbosity() !== OutputInterface::VERBOSITY_NORMAL) {
                $progress->setOverwrite(false);
            }

            for (; $generator->valid(); $generator->next()) {
                $progress->setMessage($generator->key());
                $progress->advance();
            }
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            // we must have an exit code higher than zero to indicate something was wrong
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        $output->write(PHP_EOL);
        $output->writeln("<info>Product images resized successfully</info>");
    }
}