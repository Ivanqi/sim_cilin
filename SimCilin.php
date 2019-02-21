<?php
ini_set('memory_limit', '1024M');
include_once "./deps/autoload.php";
use Fukuball\Jieba\Jieba;
use Fukuball\Jieba\Finalseg;
use Fukuball\Jieba\Posseg;
class SimCilin {
    private $cilinPath = '';
    private $semDict = [];
    private $inadmissableChinesePartOfSpeech = [];
    public function __construct()
    {
        $this->cilinPath = 'model/cilin.txt';
        $this->semDict = $this->loadSemantic(); 
        // u 助词 取英语助词 auxiliary
        // x 非语素字 非语素字只是一个符号，字母 x通常用于代表未知数、符号
        // w 标点符号
        $this->inadmissableChinesePartOfSpeech = ['u', 'x', 'w'];
        Jieba::init();
        Finalseg::init();
        Posseg::init();
    }

    /**
     * 加载语义词典
     */
    private function loadSemantic()
    {
        $semDict = [];
        $fh = fopen($this->cilinPath, 'rb');
        try {
            if (!$fh) {
                throw new \Exception('词林文件读取失败！');
            }
            $i = 0;
            while (!feof($fh)) {
                $line = explode(' ', trim(fgets($fh)));
                $semType = array_shift($line);
                $words = $line;
                foreach($words as $word) {
                    if (!isset($semDict[$word])) {
                        $semDict[$word] = $semType;
                    } else {
                        $semDict[$word] .= ';' . $semType;
                    }
                }
                $i++;
            }
            fclose($fh);
            foreach($semDict as $word => $semType) {
                $semDict[$word] = explode(';', $semType);
            }
            return $semDict;
        } catch(\Exception $e) {
            fclose($fh);
            return $semDict;
        }
    }
    
    /**
     * 比较计算词语之间的相似度, 取max最大值
     * @param $word1 string
     * @param $wrod2 string
     */
    private function computeWordSim($word1, $word2)
    {
        $semsWord1 = isset($this->semDict[$word1]) ? $this->semDict[$word1] : [];
        $semsWord2 = isset($this->semDict[$word2]) ? $this->semDict[$word2] : [];
        $scoreList = [];
        foreach ($semsWord1 as $semWord1) {
            foreach($semsWord2 as $semWord2) {
                $scoreList[] = $this->computeSem($semWord1, $semWord2);
            }
        }
        if (!empty($scoreList)) {
            return max($scoreList);
        } else {
            return 0;
        }
    }

    /**
     * 基于语义计算词语相似度
     * @param $sem1
     * @param $sem2
     */
    private function computeSem($sem1, $sem2)
    {
        $sem1 = $this->handlerSemString($sem1);
        $sem2 = $this->handlerSemString($sem2);

        $score = 0;
        for ($i = 0; $i < count($sem1); $i++) {
            if ($sem1[$i] == $sem2[$i]) {
                if(in_array($i, [0, 1])) {
                    $score += 3;
                } else if ($i == 2) {
                    $score += 2;
                } else if (in_array($i, [3, 4])) {
                    $score += 1;
                }
            }
        }
        return $score / 10;
    }

    private function handlerSemString($sem) 
    {
        return [
            substr($sem, 0, 1),
            substr($sem, 1, 1),
            substr($sem, 2, 2),
            substr($sem, 4, 1),
            substr($sem, 5, 2),
            substr($sem, -1)
        ];
    }

    /**
     * 基于词相似度计算句子相似度
     */
    public function distance($text1, $text2)
    {
        $words1 = $this->handlerPosseg($text1);
        $words2 = $this->handlerPosseg($text2);
        $scoreWords1 = [];
        $scoreWords2 = [];
        foreach($words1 as $word1) {
            $score = [];
            foreach($words2 as $word2) {
                $score[] = $this->computeWordSim($word1, $word2); 
            }
            array_push($scoreWords1, max($score));
        }
        foreach($words2 as $word2) {
            $score = [];
            foreach($words1 as $word1) {
                $score[] = $this->computeWordSim($word2, $word1);
            }
            array_push($scoreWords2, max($score));
        }
        $similarity = max([array_sum($scoreWords1) / count($words1), array_sum($scoreWords2) / count($words2)]);
        return $similarity;
    }

    private function handlerPosseg($text)
    {
        $returnPossegData = [];
        $segList = Posseg::cut($text);
        foreach($segList as $value) {
            if (in_array($value['tag'], $this->inadmissableChinesePartOfSpeech)) {
                continue;
            }
            $returnPossegData [] = $value['word'];
        }
        return $returnPossegData;
    }
}

$simCilin = new SimCilin();
$text1 = '人民';

$sampleList = ["国民", "群众", "党群", "良民", "同志", "成年人", "市民", "亲属", "志愿者", "先锋" ];

foreach($sampleList as $text2) {
    $res = $simCilin->distance($text1, $text2);
    echo $text1. ' ' . $text2.' 相似度:' . $res . PHP_EOL;
}


