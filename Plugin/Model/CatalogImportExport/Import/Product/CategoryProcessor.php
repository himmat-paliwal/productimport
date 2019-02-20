<?php
namespace Eastlane\Productimport\Plugin\Model\CatalogImportExport\Import\Product;
use Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor as MainCategoryProcessor;

class CategoryProcessor
{
    /**
     * // NOTE: Must cache the failed categories here as Magento caches them even if they have failed and as
     *          a result won't add them to the failed categories list
     * @var array
     */
    protected static $failedCategoriesCache = [];

    /**
     * [afterUpsertCategories description]
     * @param  CategoryProcessor $categoryProcessor [description]
     * @param  [type]            $categoryIds       [description]
     * @return [type]                               [description]
     */
    public function afterUpsertCategories(MainCategoryProcessor $categoryProcessor, $categoryIds)
    {
        // NOTE: Work-around for a Magento bug where it's still adding failed categories to the list
        if ($failedCategories = $categoryProcessor->getFailedCategories()) {
            foreach ($failedCategories as $failedCategory) {
                self::$failedCategoriesCache[] = $failedCategory['category']->getId();
            }
        }
        $categoryIds = array_diff($categoryIds, self::$failedCategoriesCache);
        return $categoryIds;
    }
}