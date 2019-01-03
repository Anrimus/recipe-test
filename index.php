<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fridge</title>
  <style>
    .fridge-form {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .content-wrapper {
      margin: 20% auto;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .input-group {
      display: flex;
      flex-direction: column;
      margin-top: 20px;
    }

    .submit-btn {
      margin-top: 20px;
    }


    input[type="file"] {
      display: none;
    }

    .custom-file-upload {
      border: 1px solid #ccc;
      display: inline-block;
      padding: 6px 12px;
      cursor: pointer;
    }

  </style>
</head>
<body>
<div class="content-wrapper">
  <h1 class="header">Fridge</h1>
  <form enctype="multipart/form-data" method="post" action="index.php" class="fridge-form">
    <div class="input-group">
      <label for="fridge-csv" class="custom-file-upload">
        Fridge CSV
      </label>
      <input id="fridge-csv" type="file" name="fridge"/>
    </div>
    <div class="input-group">
      <label for="recipe-json" class="custom-file-upload">
        Recipes JSON
      </label>
      <input id="recipe-json" type="file" name="recipes"/>
    </div>


    <button type="submit" class="submit-btn">Submit</button>
  </form>
</div>
</body>
</html>

<?php
if (isset($_FILES['fridge']) && isset($_FILES['fridge']['tmp_name']) && !empty($_FILES['fridge']['tmp_name'])
    && isset($_FILES['recipes']) && isset($_FILES['recipes']['tmp_name']) && !empty($_FILES['recipes']['tmp_name'])
    && checkExtensions($_FILES['fridge']['type'], $_FILES['recipes']['type'])) {
    $fridgeFileName = $_FILES['fridge']['tmp_name'];
    $recipesFileName = $_FILES['recipes']['tmp_name'];
    $fridgeFileContent = file_get_contents($fridgeFileName);
    $recipesFileContent = file_get_contents($recipesFileName);
    $availableProducts = parseFridgeData($fridgeFileContent);
    $allRecipes = json_decode($recipesFileContent);

    if (count($availableProducts) && count($allRecipes)) {
        $availableRecipes = filterRecipesByAvailableProducts($availableProducts, $allRecipes);
        if (count($availableRecipes)) {

            $closestRecipe = findClosest($availableRecipes, $availableProducts);

            // show result
            echo '<pre>';
            print_r($closestRecipe);

//            print_r($allRecipes);
//            print_r($availableProducts);
            echo '</pre>';
        } else {
            echo 'Order Takeout';
        }
    } else {
        echo 'Please, check provided files';
    }


}

// return false if extensions are invalid, otherwise - true
function checkExtensions($fridgeFileType, $recipesFileType)
{

    if ($fridgeFileType !== 'text/csv' || $recipesFileType !== 'application/json') {
        return false;
    }

    return true;
}

function parseFridgeData($fridgeFileName)
{
    $lines = preg_split("/\r?\n/", $fridgeFileName);
    $headers = str_getcsv(array_shift($lines));
    $data = [];

    foreach ($lines as $line) {
        $row = [];

        foreach (str_getcsv($line) as $key => $field) {
            $row[$headers[$key]] = $field;
        }

        $row = array_filter($row);
        $data[] = $row;
    }

    $filteredData = [];

    foreach ($data as $dataPiece) {
        // next line is there because of strtotime misinterprets given in task date
        $date = str_replace('/', '-', $dataPiece['useby']);
        if (time() < strtotime($date)) {
            $dataPiece['unixExpiry'] = strtotime($date);
            $filteredData[] = $dataPiece;
        }
    }

    return $filteredData;
}

function filterRecipesByAvailableProducts($availableProducts, $allRecipes)
{
    $validRecipes = [];

    foreach ($allRecipes as $recipe) {
        foreach ($recipe->ingredients as $ingredient) {
            $isIngredientAvailable = false;
            foreach ($availableProducts as $product) {
                if ($product['item'] === $ingredient->item
                    && $product['unit'] === $ingredient->unit
                    && $product['amount'] >= $ingredient->amount) {
                    $isIngredientAvailable = true;
                    break;
                }
            }

            // move on next recipe if any ingredient is unavailable
            if (!$isIngredientAvailable) {
                break 2;
            }
        }


        $validRecipes[] = $recipe;
    }

    return $validRecipes;
}

function findClosest($recipes, $products)
{
    $keyDate = [];

    foreach ($recipes as $key => $recipe) {
        $lowestDate = null;
        foreach ($recipe->ingredients as $ingredient) {
            $ingredientDate = null;
            foreach ($products as $product) {
                if ($ingredient->item === $product['item']) {
                    $ingredientDate = $product['unixExpiry'];
                    break;
                }
            }

            if (!$lowestDate || $lowestDate > $ingredientDate) {
                $lowestDate = $ingredientDate;
            }
        }

        $keyDate[$key] = $lowestDate;
    }

    return $recipes[array_search(min($keyDate), $keyDate)];
}
