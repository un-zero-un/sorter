<?php

namespace UnZeroUn\Sorter;

class SorterFactory
{
    /**
     * @var \UnZeroUn\Sorter\Applier\SortApplier[]
     */
    private $appliers;

    /**
     * @param \UnZeroUn\Sorter\Applier\SortApplier[] $appliers
     */
    public function __construct(array $appliers)
    {
        $this->appliers = $appliers;
    }

    /**
     * @param Definition $definition
     *
     * @return Sorter
     */
    public function createSorter(Definition $definition = null)
    {
        $sorter = new Sorter($this);
        if (null !== $definition) {
            $definition->buildSorter($sorter);
        }

        return $sorter;
    }

    /**
     * @param mixed $data
     *
     * @return Applier\SortApplier
     */
    public function getApplier($data)
    {
        foreach ($this->appliers as $applier) {
            if ($applier->supports($data)) {
                return $applier;
            }
        }

        throw new \OutOfBoundsException('No applier found for given data.');
    }
}
