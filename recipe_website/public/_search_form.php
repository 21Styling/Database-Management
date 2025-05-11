<?php
// This form will be included in other pages.
// Ensure $_GET values are pre-filled.
$current_page_script = basename($_SERVER['PHP_SELF']);
$form_action = "search_results.php"; // Default action

// Values for pre-filling the form
$searchTermVal = isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '';
$searchByVal = isset($_GET['search_by']) ? $_GET['search_by'] : 'recipe_name';

// Nutrition fields for easier iteration
$nutrition_form_fields_for_form = [
    'Calories' => 'Calories', 'Fat' => 'Fat (g)', 'Saturated_Fat' => 'Saturated Fat (g)',
    'Cholesterol' => 'Cholesterol (mg)', 'Sodium' => 'Sodium (mg)',
    'Carbohydrate' => 'Carbohydrates (g)', 'Fiber' => 'Fiber (g)',
    'Sugar' => 'Sugar (g)', 'Protein' => 'Protein (g)'
];
// Time fields
$time_form_keys_for_form = ['PrepTime' => 'Prep Time', 'CookTime' => 'Cook Time', 'TotalTime' => 'Total Time'];

?>
<section class="home-section search-section-on-page">
    <h2>Search Recipes</h2>
    <form action="<?php echo $form_action; ?>" method="get" class="recipe-search-form" id="mainSearchForm">
        <input type="text" name="q" id="searchInput" placeholder="Enter search term..." value="<?php echo $searchTermVal; ?>">
        <button type="button" id="advancedSearchBtnOnPage">Advanced Search</button>
        <button type="submit">Search</button>
        <button type="button" id="resetSearchBtn">Reset</button>

        <div id="advancedSearchOptionsOnPage" style="display:none; border: 1px solid #ccc; padding: 15px; margin-top: 10px;">
            <h4>Advanced Options</h4>
            <label for="search_by_on_page">Search By:</label>
            <select name="search_by" id="search_by_on_page">
                <option value="recipe_name" <?php if ($searchByVal === 'recipe_name') echo 'selected'; ?>>Recipe Name</option>
                <option value="keywords" <?php if ($searchByVal === 'keywords') echo 'selected'; ?>>Keywords (Name, Desc, Ingred.)</option>
                <option value="author" <?php if ($searchByVal === 'author') echo 'selected'; ?>>Author ID</option>
            </select>
            <br><br>

            <h5>Nutrition Facts (per serving):</h5>
            <?php
            foreach ($nutrition_form_fields_for_form as $field_key => $field_label) {
                $minVal = isset($_GET['min_' . $field_key]) ? htmlspecialchars($_GET['min_' . $field_key]) : '';
                $maxVal = isset($_GET['max_' . $field_key]) ? htmlspecialchars($_GET['max_' . $field_key]) : '';
                echo '<label for="min_' . $field_key . '_on_page">Min ' . $field_label . ':</label>';
                echo '<input type="number" name="min_' . $field_key . '" id="min_' . $field_key . '_on_page" step="any" value="' . $minVal . '" min="0">';
                echo '<label for="max_' . $field_key . '_on_page">Max ' . $field_label . ':</label>';
                echo '<input type="number" name="max_' . $field_key . '" id="max_' . $field_key . '_on_page" step="any" value="' . $maxVal . '" min="0">';
                echo '<br>';
            }
            ?>

            <h5>Recipe Yield:</h5>
            <?php
                $minRecipeServingsVal = isset($_GET['min_RecipeServings']) ? htmlspecialchars($_GET['min_RecipeServings']) : '';
                $maxRecipeServingsVal = isset($_GET['max_RecipeServings']) ? htmlspecialchars($_GET['max_RecipeServings']) : '';
            ?>
            <label for="min_RecipeServings_on_page">Min Servings (from Recipe):</label>
            <input type="number" name="min_RecipeServings" id="min_RecipeServings_on_page" value="<?php echo $minRecipeServingsVal; ?>" min="0">
            <label for="max_RecipeServings_on_page">Max Servings (from Recipe):</label>
            <input type="number" name="max_RecipeServings" id="max_RecipeServings_on_page" value="<?php echo $maxRecipeServingsVal; ?>" min="0">
            <br><br>

            <h5>Time:</h5>
            <?php
            foreach ($time_form_keys_for_form as $field_key => $field_label) {
                $minHrVal = isset($_GET['min_' . $field_key . '_hr']) ? htmlspecialchars($_GET['min_' . $field_key . '_hr']) : '';
                $minMinVal = isset($_GET['min_' . $field_key . '_min']) ? htmlspecialchars($_GET['min_' . $field_key . '_min']) : '';
                $maxHrVal = isset($_GET['max_' . $field_key . '_hr']) ? htmlspecialchars($_GET['max_' . $field_key . '_hr']) : '';
                $maxMinVal = isset($_GET['max_' . $field_key . '_min']) ? htmlspecialchars($_GET['max_' . $field_key . '_min']) : '';

                echo '<div class="time-input-set">';
                echo '<strong>' . $field_label . ':</strong><br>';
                // MINIMUM
                echo '<label for="min_' . $field_key . '_hr_on_page">Min:</label>';
                echo '<span class="time-input-group">';
                echo '<input type="number" name="min_' . $field_key . '_hr" id="min_' . $field_key . '_hr_on_page" placeholder="hr" value="' . $minHrVal . '" min="0" max="838">';
                echo '<span>hrs</span>';
                echo '<input type="number" name="min_' . $field_key . '_min" id="min_' . $field_key . '_min_on_page" placeholder="min" value="' . $minMinVal . '" min="0" max="59">';
                echo '<span>min</span>';
                echo '</span>';
                // MAXIMUM
                echo '<label for="max_' . $field_key . '_hr_on_page" style="margin-left:20px;">Max:</label>';
                echo '<span class="time-input-group">';
                echo '<input type="number" name="max_' . $field_key . '_hr" id="max_' . $field_key . '_hr_on_page" placeholder="hr" value="' . $maxHrVal . '" min="0" max="838">';
                echo '<span>hrs</span>';
                echo '<input type="number" name="max_' . $field_key . '_min" id="max_' . $field_key . '_min_on_page" placeholder="min" value="' . $maxMinVal . '" min="0" max="59">';
                echo '<span>min</span>';
                echo '</span>';
                echo '<br>';
                echo '</div>';
            }
            ?>
        </div>
    </form>
</section>

<script>
// JavaScript for toggling advanced search and handling reset
// We put this here so it's always included with the form
document.addEventListener('DOMContentLoaded', function() {
    const advancedSearchBtn = document.getElementById('advancedSearchBtnOnPage');
    const advancedSearchOptions = document.getElementById('advancedSearchOptionsOnPage');
    const resetSearchBtn = document.getElementById('resetSearchBtn');
    const mainSearchForm = document.getElementById('mainSearchForm');
    const searchInput = document.getElementById('searchInput'); // The main search text input

    if(advancedSearchBtn && advancedSearchOptions) {
        advancedSearchBtn.addEventListener('click', function() {
            if (advancedSearchOptions.style.display === 'none' || advancedSearchOptions.style.display === '') {
                advancedSearchOptions.style.display = 'block';
                this.textContent = 'Hide Advanced';
            } else {
                advancedSearchOptions.style.display = 'none';
                this.textContent = 'Advanced Search';
            }
        });

        // Logic to check URL params and keep advanced section open if any advanced param is set
        const urlParams = new URLSearchParams(window.location.search);
        let advancedActive = false;
        const advancedParamKeys = [
            'search_by', 'min_Calories', 'max_Calories', 'min_Fat', 'max_Fat',
            'min_Saturated_Fat', 'max_Saturated_Fat', 'min_Cholesterol', 'max_Cholesterol',
            'min_Sodium', 'max_Sodium', 'min_Carbohydrate', 'max_Carbohydrate',
            'min_Fiber', 'max_Fiber', 'min_Sugar', 'max_Sugar', 'min_Protein', 'max_Protein',
            'min_RecipeServings', 'max_RecipeServings',
            'min_PrepTime_hr', 'min_PrepTime_min', 'max_PrepTime_hr', 'max_PrepTime_min',
            'min_CookTime_hr', 'min_CookTime_min', 'max_CookTime_hr', 'max_CookTime_min',
            'min_TotalTime_hr', 'min_TotalTime_min', 'max_TotalTime_hr', 'max_TotalTime_min'
        ];

        // Check if any advanced parameter (excluding 'q') has a value, or if 'search_by' is not the default.
        for (const key of advancedParamKeys) {
            if (urlParams.has(key) && urlParams.get(key) !== '') {
                if (key === 'search_by' && urlParams.get(key) === 'recipe_name') {
                    // If search_by is recipe_name, only consider it advanced if other filters are also set
                    for (const otherKey of advancedParamKeys) {
                        if (otherKey !== 'search_by' && urlParams.has(otherKey) && urlParams.get(otherKey) !== '') {
                            advancedActive = true; break;
                        }
                    }
                } else {
                     advancedActive = true; // Any other advanced key with a value, or search_by not default
                }
                if (advancedActive) break;
            }
        }
        // Also consider 'q' empty but other filters set as a reason to show advanced
        if (!urlParams.has('q') || urlParams.get('q') === '') {
            for (const key of advancedParamKeys) {
                 if (key !== 'search_by' && urlParams.has(key) && urlParams.get(key) !== '') {
                     advancedActive = true; break;
                 }
            }
        }


        if (advancedActive) {
            advancedSearchOptions.style.display = 'block';
            if(advancedSearchBtn) advancedSearchBtn.textContent = 'Hide Advanced';
        }
    }

    if (resetSearchBtn && mainSearchForm) {
        resetSearchBtn.addEventListener('click', function() {
            // Clear all input fields and select elements within the form
            mainSearchForm.reset(); // This resets form elements to their default values specified in HTML

            // Specifically clear values that might not be reset by form.reset() if they were dynamically set
            // or if you want to ensure they are empty strings.
            const inputs = mainSearchForm.querySelectorAll('input[type="text"], input[type="number"]');
            inputs.forEach(input => input.value = '');

            const selects = mainSearchForm.querySelectorAll('select');
            selects.forEach(select => select.selectedIndex = 0); // Reset to the first option

            // Redirect to the current page without any query parameters
            // This effectively "resets" the search state.
            window.location.href = window.location.pathname;
        });
    }
});
</script>