<?php

namespace Rubix\ML\Transformers;

use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Datasets\DataFrame;
use Rubix\ML\Other\Helpers\Stats;
use RuntimeException;

/**
 * Z Scale Standardizer
 *
 * A way of centering and scaling a sample matrix by computing the Z Score for
 * each feature.
 * 
 * References:
 * [1] T. F. Chan et al. (1979). Updating Formulae and a Pairwise Algorithm for
 * Computing Sample Variances.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class ZScaleStandardizer implements Transformer, Elastic
{
    /**
     * Should we center the data?
     *
     * @var bool
     */
    protected $center;

    /**
     * The means of each feature column from the fitted data.
     *
     * @var array|null
     */
    protected $means;

    /**
     * The variances of each feature column from the fitted data.
     *
     * @var array|null
     */
    protected $variances;

    /**
     *  The number of samples that this tranformer has fitted.
     * 
     * @var int|null
     */
    protected $n;

    /**
     * The precomputed standard deviations.
     *
     * @var array|null
     */
    protected $stddevs;

    /**
     * @param  bool  $center
     * @return void
     */
    public function __construct(bool $center = true)
    {
        $this->center = $center;
    }

    /**
     * Return the means calculated by fitting the training set.
     *
     * @return array|null
     */
    public function means() : ?array
    {
        return $this->means;
    }

    /**
     * Return the variances calculated by fitting the training set.
     *
     * @return array|null
     */
    public function variances() : ?array
    {
        return $this->variances;
    }

    /**
     * Return the standard deviations calculated during fitting.
     *
     * @return array|null
     */
    public function stddevs() : ?array
    {
        return $this->stddevs;
    }

    /**
     * Fit the transformer to the dataset.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @return void
     */
    public function fit(Dataset $dataset) : void
    {
        $columns = $dataset->columnsByType(DataFrame::CONTINUOUS);

        $this->means = $this->variances = $this->stddevs = [];

        foreach ($columns as $column => $values) {
            list($mean, $variance) = Stats::meanVar($values);

            $this->means[$column] = $mean;
            $this->variances[$column] = $variance;
            $this->stddevs[$column] = sqrt($variance ?: self::EPSILON);
        }

        $this->n = $dataset->numRows();
    }

    /**
     * Update the fitting of the transformer.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @return void
     */
    public function update(Dataset $dataset) : void
    {
        if (is_null($this->means) or is_null($this->variances)) {
            $this->fit($dataset);
            return;
        }

        $n = $dataset->numRows();

        foreach ($this->means as $column => $oldMean) {
            $oldVariance = $this->variances[$column];

            $values = $dataset->column($column);

            list($mean, $variance) = Stats::meanVar($values);

            $this->means[$column] = (($n * $mean)
                + ($this->n * $oldMean))
                / ($this->n + $n);

            $varNew = ($this->n
                * $oldVariance + ($n * $variance)
                + ($this->n / ($n * ($this->n + $n)))
                * ($n * $oldMean - $n * $mean) ** 2)
                / ($this->n + $n);

            $this->variances[$column] = $varNew;
            $this->stddevs[$column] = sqrt($varNew ?: self::EPSILON);
        }

        $this->n += $n;
    }

    /**
     * Transform the dataset in place.
     *
     * @param  array  $samples
     * @param  array|null  $labels
     * @throws \RuntimeException
     * @return void
     */
    public function transform(array &$samples, ?array &$labels = null) : void
    {
        if (is_null($this->means) or is_null($this->stddevs)) {
            throw new RuntimeException('Transformer has not been fitted.');
        }

        foreach ($samples as &$sample) {
            foreach ($this->stddevs as $column => $stddev) {
                $feature = $sample[$column];

                if ($this->center) {
                    $feature -= $this->means[$column];
                }

                $sample[$column] = $feature / $stddev;
            }
        }
    }
}
