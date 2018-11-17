<?php

namespace Rubix\ML\CrossValidation\Metrics;

use Rubix\ML\Estimator;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Other\Helpers\Stats;
use Rubix\ML\Datasets\DataFrame;
use InvalidArgumentException;

/**
 * Concentration
 *
 * An unsupervised metric that measures the ratio between the within-cluster
 * dispersion and the between-cluster dispersion (also called *Calinski-Harabaz*
 * score).
 * 
 * References:
 * [1] T. Calinski et al. (1974). A dendrite method for cluster analysis.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class Concentration implements Metric
{
    /**
     * Return a tuple of the min and max output value for this metric.
     *
     * @return float[]
     */
    public function range() : array
    {
        return [-INF, INF];
    }

    /**
     * Calculate the Calinski Harabaz score of a clustering. The score
     * is defined as ratio between the within-cluster dispersion and the
     * between-cluster dispersion.
     *
     * @param  \Rubix\ML\Estimator  $estimator
     * @param  \Rubix\ML\Datasets\Dataset  $testing
     * @throws \InvalidArgumentException
     * @return float
     */
    public function score(Estimator $estimator, Dataset $testing) : float
    {
        if ($estimator->type() !== Estimator::CLUSTERER) {
            throw new InvalidArgumentException('This metric only works with'
                . ' clusterers.');
        }

        if (in_array(DataFrame::CATEGORICAL, $testing->types())) {
            throw new InvalidArgumentException('This metric only works with'
                . ' continuous features.');
        }

        $n = $testing->numRows();

        if ($n < 1) {
            return 0.;
        }

        $predictions = $estimator->predict($testing);

        $labeled = Labeled::quick($testing->samples(), $predictions);

        $globals = array_map([Stats::class, 'mean'], $testing->columns());

        $strata = $labeled->stratify();

        $k = count($strata);

        $intra = $extra = 0.;

        foreach ($strata as $cluster => $stratum) {
            $centroid = [];

            foreach ($stratum->columns() as $column => $values) {
                $mean = Stats::mean((array) $values);

                $dispersion = ($mean - $globals[$column]) ** 2;

                $extra += $dispersion * $stratum->numRows();

                $centroid[] = $mean;
            }

            foreach ($stratum as $row => $sample) {
                foreach ($sample as $column => $feature) {
                    $intra += ($feature - $centroid[$column]) ** 2;
                }
            }
        }

        if ($intra === 0.) {
            return 1.;
        }

        return $extra * ($n - $k) / $intra * ($k - 1);
    }
}
