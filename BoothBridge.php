<?php
class BoothBridge extends BridgeAbstract {
    const NAME = 'BOOTH';
    const URI = 'https://booth.pm/';
    const DESCRIPTION = 'BOOTHの新着アイテムを取得します';
    const MAINTAINER = 'Shadero';
    const PARAMETERS = array(
        '検索' => array(
            'keyword' => array(
                'name' => 'キーワード',
                'type' => 'text',
                'required' => false,
                'exampleValue' => 'VRChat'
            ),
            'exclude_keyword' => array(
                'name' => '除外キーワード',
                'type' => 'text',
                'required' => false,
                'exampleValue' => '除外1,除外2'
            ),
            'tag' => array(
                'name' => 'タグ名',
                'type' => 'text',
                'required' => false,
                'exampleValue' => 'しなの,Lapwing'
            ),
            'category' => array(
                'name' => 'カテゴリー',
                'type' => 'text',
                'required' => false,
                'exampleValue' => '3D衣装'
            ),
            'item_type' => array(
                'name' => '商品タイプ',
                'type' => 'list',
                'required' => false,
                'defaultValue' => 'all',
                'values' => array(
                    '指定なし' => 'all',
                    'ダウンロード商品' => 'digital',
                    '物販' => 'physical',
                    '物販（自宅から発送）' => 'direct',
                    '物販（倉庫から発送）' => 'via_warehouse',
                    '物販（pixivFACTORYから発送）' => 'factory_item',
                )
            ),
            'min_price' => array(
                'name' => '最小価格',
                'type' => 'number',
                'required' => false,
                'exampleValue' => '300'
            ),
            'max_price' => array(
                'name' => '最大価格',
                'type' => 'number',
                'required' => false,
                'exampleValue' => '5000'
            ),
            'age' => array(
                'name' => '年齢制限',
                'type' => 'list',
                'required' => false,
                'defaultValue' => 'all-ages',
                'values' => array(
                    '全年齢のみ' => 'all-ages',
                    'R18商品のみ' => 'only',
                    '指定なし' => 'include'
                )
            ),
        )
    );

    public function collectData() {
        $keyword = trim((string)$this->getInput('keyword'));
        $category = trim((string)$this->getInput('category'));
        $excludeWords = preg_split('/[\s,、]+/u', trim((string)$this->getInput('exclude_keyword')), -1, PREG_SPLIT_NO_EMPTY);
        $age = $this->getInput('age') ?: 'all-ages';
        $minPrice = $this->getInput('min_price');
        $maxPrice = $this->getInput('max_price');
        $tags = preg_split('/[\s,、]+/u', trim($this->getInput('tag')), -1, PREG_SPLIT_NO_EMPTY);
        $itemType = $this->getInput('item_type');
        $queryParts = array('sort=new');
        
        foreach ($excludeWords as $word) {
            $queryParts[] = 'except_words%5B%5D=' . rawurlencode($word);
        }
        
        if ($age === 'only') {
            $queryParts[] = 'adult=only';
        } elseif ($age === 'include') {
            $queryParts[] = 'adult=include';
        }

        if ($minPrice !== null && $minPrice !== '') {
            $queryParts[] = 'min_price=' . rawurlencode((string)$minPrice);
        }

        if ($maxPrice !== null && $maxPrice !== '') {
            $queryParts[] = 'max_price=' . rawurlencode((string)$maxPrice);
        }

        foreach ($tags as $singleTag) {
            $queryParts[] = 'tags%5B%5D=' . rawurlencode($singleTag);
        }

        if ($itemType !== 'all') {
            $queryParts[] = 'type=' . rawurlencode($itemType);
        }

        $baseUrl = '';
        if ($keyword !== '') {
            $baseUrl = 'https://booth.pm/ja/search/' . rawurlencode($keyword);
        } if ($category !== '') {
            $baseUrl = 'https://booth.pm/ja/browse/' . rawurlencode($category);
            $queryParts[] = 'q=' . rawurlencode($keyword);
        }
        else {
            $baseUrl = 'https://booth.pm/ja/items';
        }
        $url = $baseUrl . '?' . implode('&', $queryParts);


        $headers = array('Cookie: adult=t');
        $html = getSimpleHTMLDOM($url, $headers);

        foreach($html->find('.item-card') as $item) {
            $titleElement = $item->find('.item-card__title', 0);
            $linkElement = $item->find('a', 0);
            $authorElement = $item->find('.item-card__shop-name', 0);
            $priceElement = $item->find('.price', 0);
            $thumbnailElement = $item->find('.item-card__thumbnail-image', 0);
            
            $title = $titleElement ? trim($titleElement->plaintext) : 'タイトル不明';
            $uri = $linkElement ? trim($linkElement->href) : '';
            $author = $authorElement ? trim($authorElement->plaintext) : '不明なショップ';
            $price = $priceElement ? trim($priceElement->plaintext) : '価格不明';
            $imgHtml = '';
            if ($thumbnailElement) {
                $imgSrc = '';
                $style = $thumbnailElement->getAttribute('style');
                if (preg_match('/background-image:\s*url\((["\']?)([^"\']+)\1\)/i', $style, $matches)) {
                    $imgSrc = $matches[2];
                }
                if (!empty($imgSrc)) {
                    $imgHtml = sprintf('<img src="%s" /><br>', htmlspecialchars($imgSrc));
                }
            }

            $itemArray = array();
            $itemArray['title'] = $title;
            $itemArray['uri'] = $uri;
            $itemArray['author'] = $author;
            $itemArray['content'] = $imgHtml . '<strong>ショップ:</strong> ' . htmlspecialchars($author) . '<br><strong>価格:</strong> ' . htmlspecialchars($price);

            $this->items[] = $itemArray;
        }
    }
}
