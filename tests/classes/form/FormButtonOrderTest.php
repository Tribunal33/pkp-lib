<?php

/**
 * @file tests/classes/form/FormButtonOrderTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormButtonOrderTest
 *
 * @ingroup tests_classes_form
 *
 * @brief Test class for form button order fix (GitHub issue #3).
 * Tests that the formButtons.tpl template renders buttons in the correct order:
 * Go Back, Save for Later, Submit Review (NOT Go Back, Submit Review, Save for Later)
 * 
 * This test uses actual template markup elements instead of comments to ensure
 * robustness even if comments are removed or modified.
 */

namespace PKP\tests\classes\form;

use PHPUnit\Framework\TestCase;

/**
 * Tests that use actual template markup elements instead of comments to ensure
 * robustness even if comments are removed or modified.
 */
class FormButtonOrderTest extends TestCase
{
    private $templatePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templatePath = dirname(__FILE__) . '/../../../templates/form/formButtons.tpl';
    }

    /**
     * Test that the formButtons.tpl template file exists and is readable
     */
    public function testTemplateFileExists()
    {
        $this->assertFileExists($this->templatePath, 'formButtons.tpl template file should exist');
        $this->assertFileIsReadable($this->templatePath, 'formButtons.tpl template file should be readable');
    }

    /**
     * Test that the template contains the expected button sections in the correct order
     * This test uses actual markup elements instead of comments to avoid fragility
     */
    public function testButtonOrderInTemplate()
    {
        $templateContent = file_get_contents($this->templatePath);
        
        // Verify template contains expected button elements (not comments)
        $this->assertStringContainsString('class="cancelButton"', $templateContent, 'Template should contain cancel button element');
        $this->assertStringContainsString('class="saveFormButton"', $templateContent, 'Template should contain save button element');
        $this->assertStringContainsString('class="{if $FBV_saveText}pkp_button_primary{/if} submitFormButton"', $templateContent, 'Template should contain submit button element');
        
        // Check the order by finding line positions of actual elements
        $lines = explode("\n", $templateContent);
        $cancelLine = $this->findLineWithElement($lines, 'class="cancelButton"');
        $saveLine = $this->findLineWithElement($lines, 'class="saveFormButton"');
        $submitLine = $this->findLineWithElement($lines, 'submitFormButton"');
        
        // Verify all button elements were found
        $this->assertGreaterThan(-1, $cancelLine, 'Cancel button element should be found');
        $this->assertGreaterThan(-1, $saveLine, 'Save button element should be found');
        $this->assertGreaterThan(-1, $submitLine, 'Submit button element should be found');
        
        // Test the order: Cancel < Save < Submit (GitHub issue #3 fix)
        $this->assertLessThan($saveLine, $cancelLine, 'Cancel button element should appear before Save button element');
        $this->assertLessThan($submitLine, $saveLine, 'Save button element should appear before Submit button element');
    }

    /**
     * Test that the actual button generation elements are in the correct order
     * This test focuses on the fbvElement markup instead of comments
     */
    public function testButtonGenerationOrder()
    {
        $templateContent = file_get_contents($this->templatePath);
        $lines = explode("\n", $templateContent);
        
        // Find the actual button generation lines using specific identifiers
        $saveButtonLine = $this->findLineWithElement($lines, 'name="saveFormButton"');
        $submitButtonLine = $this->findLineWithElement($lines, 'name="submitFormButton"');
        
        // Verify both button generation lines were found
        $this->assertGreaterThan(-1, $saveButtonLine, 'Save button generation should be found');
        $this->assertGreaterThan(-1, $submitButtonLine, 'Submit button generation should be found');
        
        // The critical test: Save button should be generated before Submit button
        $this->assertLessThan($submitButtonLine, $saveButtonLine, 'Save button should be generated before Submit button (GitHub issue #3 fix)');
    }

    /**
     * Test that the template produces the expected button order when analyzed
     * This test uses conditional blocks and element structure instead of comments
     */
    public function testTemplateStructureButtonOrder()
    {
        // Test the expected button order by analyzing template structure
        $expectedOrder = ['Go Back', 'Save for Later', 'Submit Review'];
        $actualOrder = $this->getButtonOrderFromTemplateStructure();
        
        $this->assertEquals($expectedOrder, $actualOrder, 'Button order should match GitHub issue #3 requirements');
    }
    
    /**
     * Extract button order from template structure using actual elements
     * This method analyzes conditional blocks and element positions, not comments
     */
    private function getButtonOrderFromTemplateStructure(): array
    {
        $templateContent = file_get_contents($this->templatePath);
        $lines = explode("\n", $templateContent);
        
        $buttonOrder = [];
        $sectionPositions = [];
        
        // Find actual element positions to determine order
        foreach ($lines as $lineNum => $line) {
            // Look for cancel button (actual element, not comment)
            if (strpos($line, 'class="cancelButton"') !== false) {
                $sectionPositions['cancel'] = $lineNum;
            }
            // Look for save button conditional block start (exact match to avoid submit button line)
            elseif (trim($line) === '{if $FBV_saveText}') {
                $sectionPositions['save'] = $lineNum;
            }
            // Look for submit button assignment (unique identifier)
            elseif (strpos($line, '{assign var=submitButtonId') !== false) {
                $sectionPositions['submit'] = $lineNum;
            }
        }
        
        // Sort by line position and create expected order
        asort($sectionPositions);
        foreach (array_keys($sectionPositions) as $section) {
            switch ($section) {
                case 'cancel':
                    $buttonOrder[] = 'Go Back';
                    break;
                case 'save':
                    $buttonOrder[] = 'Save for Later';
                    break;
                case 'submit':
                    $buttonOrder[] = 'Submit Review';
                    break;
            }
        }
        
        return $buttonOrder;
    }

    /**
     * Helper method to find a line containing specific element markup
     * This focuses on actual template elements instead of comments
     */
    private function findLineWithElement(array $lines, string $searchText): int
    {
        foreach ($lines as $lineNum => $line) {
            if (strpos($line, $searchText) !== false) {
                return $lineNum;
            }
        }
        return -1;
    }

    /**
     * Test that conditional blocks appear in the correct order
     * This test focuses on the {if} blocks that control button visibility
     */
    public function testConditionalBlockOrder()
    {
        $templateContent = file_get_contents($this->templatePath);
        $lines = explode("\n", $templateContent);
        
        // Find conditional blocks that control button rendering
        $cancelConditionLine = $this->findLineWithElement($lines, '{if !$FBV_hideCancel}');
        $saveConditionLine = $this->findLineWithElement($lines, '{if $FBV_saveText}');
        
        // Find submit button ID assignment (appears before submit button rendering)
        $submitIdLine = $this->findLineWithElement($lines, '{assign var=submitButtonId');
        
        // Verify all conditional elements were found
        $this->assertGreaterThan(-1, $cancelConditionLine, 'Cancel button conditional should be found');
        $this->assertGreaterThan(-1, $saveConditionLine, 'Save button conditional should be found');
        $this->assertGreaterThan(-1, $submitIdLine, 'Submit button ID assignment should be found');
        
        // Test the order: Cancel condition < Save condition < Submit setup
        $this->assertLessThan($saveConditionLine, $cancelConditionLine, 'Cancel conditional should appear before Save conditional');
        $this->assertLessThan($submitIdLine, $saveConditionLine, 'Save conditional should appear before Submit setup');
    }
}