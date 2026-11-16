<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Merge\ConfigNormalizationAudit;
use App\Domain\Config\Merge\ConfigNormalizer;
use App\Domain\Config\Split\ConfigSplitter;

/**
 * The audit that normalizing changed nothing but what is documented: it walks the config as it was and the normalized
 * config, and every difference has to be on the list. It does not use the normalizer.
 */
class ConfigNormalizationAuditTest extends ConfigTestCase
{
    /**
     * @return string[]
     */
    private static function audit(\stdClass $source, \stdClass $result): array
    {
        return new ConfigNormalizationAudit()->differences($source, $result);
    }

    private static function datamodel(string $json): \stdClass
    {
        return ConfigFactory::json('{"datamodel": ' . $json . '}');
    }

    public function testAConfigAndItsNormalizedFormHaveNoUndocumentedDifferences(): void
    {
        $config = ConfigFactory::config([
            ConfigFactory::layer(
                'X_A',
                'A',
                ['layer_information' => 'text', 'layer_tooltip' => 'tip', 'layer_width' => 5]
            ),
            ConfigFactory::layer('X_B', 'B'),
        ]);

        $this->assertSame([], self::audit($config, new ConfigNormalizer()->normalize($config)));
    }

    public function testTheRealConfigsAndTheirNormalizedFormHaveNoUndocumentedDifferences(): void
    {
        foreach (self::realConfigs() as $id => $original) {
            $this->assertSame([], self::audit($original, new ConfigNormalizer()->normalize($original)), $id);
        }
    }

    public function testAFieldThatIsLostIsFound(): void
    {
        $config = ConfigFactory::config([ConfigFactory::layer('X_A', 'A', ['layer_tooltip' => 'tip'])]);
        $normalized = new ConfigNormalizer()->normalize($config);
        unset($normalized->datamodel->meta[0]->layer_tooltip); // what a normalizer with a mistake could do

        $problems = self::audit($config, $normalized);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('layer_tooltip', $problems[0]);
        $this->assertStringContainsString('"X_A"', $problems[0], 'the layer is named');
        $this->assertStringContainsString('only in the original', $problems[0]);
    }

    public function testAFieldThatIsAddedIsFound(): void
    {
        $source = self::datamodel('{"meta": [{"layer_name": "A"}]}');
        $result = self::datamodel('{"meta": [{"layer_name": "A", "extra": 1}]}');

        $problems = self::audit($source, $result);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('extra: only in the normalized config', $problems[0]);
    }

    public function testAChangedValueIsFound(): void
    {
        $source = self::datamodel('{"meta": [{"layer_name": "A", "layer_short": "Alpha"}], "edition_name": "one"}');
        $result = self::datamodel('{"meta": [{"layer_name": "A", "layer_short": "Beta"}], "edition_name": "one"}');

        $problems = self::audit($source, $result);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('layer_short: "Alpha" became "Beta"', $problems[0]);
    }

    public function testANumberThatIsWrittenAnotherWayIsTheSameNumber(): void
    {
        $source = self::datamodel('{"x": {"width": 1.0, "height": 2}, "list": [3.0, 4]}');
        $result = self::datamodel('{"x": {"width": 1, "height": 2.0}, "list": [3, 4.0]}');
        $changed = self::datamodel('{"x": {"width": 1.5, "height": 2}, "list": [3, 4]}');

        $problems = self::audit($source, $changed);

        $this->assertSame([], self::audit($source, $result), '1.0 and 1 are the same number');
        $this->assertCount(1, $problems, '1.0 and 1.5 are not');
        $this->assertStringContainsString('/datamodel/x/width: 1.0 became 1.5', $problems[0]);
    }

    public function testAKeyOrderThatIsDifferentIsNotADifference(): void
    {
        $source = self::datamodel('{"a": 1, "b": {"x": 1, "y": 2}, "meta": [{"layer_name": "A", "layer_short": "S"}]}');
        $result = self::datamodel('{"meta": [{"layer_short": "S", "layer_name": "A"}], "b": {"y": 2, "x": 1}, "a": 1}');

        $this->assertSame([], self::audit($source, $result));
    }

    public function testLayersAreTheSameLayersInTheSameOrder(): void
    {
        $source = self::datamodel('{"meta": [{"layer_name": "A"}, {"layer_name": "B"}, {"layer_name": "C"}]}');

        $missing = self::audit($source, self::datamodel('{"meta": [{"layer_name": "A"}, {"layer_name": "B"}]}'));
        $swapped = self::audit(
            $source,
            self::datamodel('{"meta": [{"layer_name": "B"}, {"layer_name": "A"}, {"layer_name": "C"}]}')
        );

        $this->assertSame(['/datamodel/meta: 3 layers became 2'], $missing);
        $this->assertCount(2, $swapped);
    }

    public function testTheDocumentedRemovalsAreAllowed(): void
    {
        $source = self::datamodel(
            '{"meta": [{"layer_name": "A", "layer_geotype": "polygon", "layer_information": "i",'
            . ' "layer_raster_filter_mode": "m", "layer_width": 10, "layer_height": 20, "x": 1},'
            . ' {"layer_name": "R", "layer_geotype": "raster",'
            . ' "layer_width": 10, "layer_information": "i", "layer_raster_filter_mode": "m"}]}'
        );
        $result = self::datamodel(
            '{"meta": [{"layer_name": "A", "layer_geotype": "polygon", "x": 1}, {"layer_name": "R",'
            . ' "layer_geotype": "raster", "layer_width": 10}]}'
        );
        $widthLost = self::datamodel(
            '{"meta": [{"layer_name": "A", "layer_geotype": "polygon", "x": 1}, {"layer_name": "R",'
            . ' "layer_geotype": "raster"}]}'
        );

        $this->assertSame([], self::audit($source, $result));
        $problems = self::audit($source, $widthLost);
        $this->assertCount(1, $problems, 'layer_width is only removed from layers that are not raster layers');
        $this->assertStringContainsString('layer_width', $problems[0]);
    }

    public function testTheAllowedRemovalsAreTheDocumentedOnes(): void
    {
        $raster = ConfigSplitter::removedLayerKeys((object)['layer_geotype' => 'raster']);
        $polygon = ConfigSplitter::removedLayerKeys((object)['layer_geotype' => 'polygon']);

        $this->assertEqualsCanonicalizing(ConfigNormalizationAudit::REMOVED_LAYER_KEYS, $raster);
        $this->assertEqualsCanonicalizing(
            array_merge(
                ConfigNormalizationAudit::REMOVED_LAYER_KEYS,
                ConfigNormalizationAudit::REMOVED_NON_RASTER_LAYER_KEYS
            ),
            $polygon
        );
    }

    public function testRestrictionsAreComparedAsASetOfEntries(): void
    {
        $a = '{"startlayer": "A", "endlayer": "B", "value": 1}';
        $b = '{"startlayer": "B", "endlayer": "A", "value": 2.0}';
        $source = self::datamodel('{"meta": [], "restrictions": {"A|B": [' . $a . ', ' . $b . ']}}');

        $regrouped = self::datamodel('{"meta": [], "restrictions": {"B|A": [' . $b . '], "A|B": [' . $a . ']}}');
        $lost = self::datamodel('{"meta": [], "restrictions": {"A|B": [' . $a . ']}}');
        $changed = self::datamodel(
            '{"meta": [], "restrictions": {"A|B": [' . $a . '],'
            . ' "B|A": [{"startlayer": "B", "endlayer": "A", "value": 3}]}}'
        );

        $this->assertSame([], self::audit($source, $regrouped));
        $this->assertCount(1, self::audit($source, $lost));
        $this->assertStringContainsString('only in the original', self::audit($source, $lost)[0]);
        $this->assertCount(2, self::audit($source, $changed), 'an entry with another value is another entry');
    }

    public function testSimulationsMoveIntoSimulationSettings(): void
    {
        $source = self::datamodel('{"meta": [], "CEL": {"a": 1}, "SEL": {"b": 2}}');
        $moved = self::datamodel(
            '{"meta": [], "simulation_settings": {"CEL": {"a": 1}, "MEL": null, "SEL": {"b": 2}}}'
        );
        $changed = self::datamodel(
            '{"meta": [], "simulation_settings": {"CEL": {"a": 9}, "MEL": null, "SEL": {"b": 2}}}'
        );
        $lost = self::datamodel('{"meta": [], "simulation_settings": {"CEL": {"a": 1}, "MEL": null}}');
        $invented = self::datamodel(
            '{"meta": [], "simulation_settings": {"CEL": {"a": 1}, "MEL": {"x": 1}, "SEL": {"b": 2}}}'
        );
        $extra = self::datamodel(
            '{"meta": [], "simulation_settings": {"CEL": {"a": 1}, "MEL": null, "SEL": {"b": 2}, "REL": null}}'
        );

        $this->assertSame([], self::audit($source, $moved), 'moved, and MEL is null because there was none');
        $this->assertStringContainsString('/CEL/a: 1 became 9', self::audit($source, $changed)[0]);
        $this->assertStringContainsString('SEL: only in the original', self::audit($source, $lost)[0]);
        $this->assertStringContainsString('MEL: only in the normalized config', self::audit($source, $invented)[0]);
        $this->assertStringContainsString('REL: only in the normalized config', self::audit($source, $extra)[0]);
    }

    public function testASimulationInBothShapesIsNotAuditedButReported(): void
    {
        $source = self::datamodel('{"meta": [], "CEL": {"a": 1}, "simulation_settings": {"CEL": {"b": 2}}}');
        $result = self::datamodel(
            '{"meta": [], "simulation_settings": {"CEL": {"a": 1, "b": 2}, "MEL": null, "SEL": null}}'
        );

        $problems = self::audit($source, $result);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('as a key of datamodel and in simulation_settings', $problems[0]);
    }

    public function testTheValueDefinitionsOfMelCategoriesMayBeRemovedAndNothingElse(): void
    {
        $source = self::datamodel(
            '{"meta": [], "simulation_settings": {"MEL": {"ecologyCategories":'
            . ' [{"name": "x", "valueDefinitions": [1, 2]}]}}}'
        );
        $result = self::datamodel(
            '{"meta": [], "simulation_settings": {"MEL": {"ecologyCategories": [{"name": "x"}]},'
            . ' "CEL": null, "SEL": null}}'
        );
        $nameLost = self::datamodel(
            '{"meta": [], "simulation_settings": {"MEL": {"ecologyCategories": [{}]}, "CEL": null, "SEL": null}}'
        );

        $this->assertSame([], self::audit($source, $result));
        $this->assertStringContainsString('name: only in the original', self::audit($source, $nameLost)[0]);
    }
}
