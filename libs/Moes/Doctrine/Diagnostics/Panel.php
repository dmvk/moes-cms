<?php
/**
 * This file is part of the Nella Framework.
 *
 * Copyright (c) 2006, 2011 Patrik Votoček (http://patrik.votocek.cz)
 *
 * This source file is subject to the GNU Lesser General Public License. For more information please see http://nella-project.org
 */

namespace Moes\Doctrine\Diagnostics;

use Nette\Diagnostics\Debugger,
Nette\Database\Connection;

/**
 * Debug panel for Doctrine
 *
 * @author	David Grudl
 * @author	Patrik Votoček
 */
class Panel extends \Nette\Object implements \Nette\Diagnostics\IBarPanel, \Doctrine\DBAL\Logging\SQLLogger
{
    /** @var int logged time */
    public $totalTime = 0;

    /** @var array */
    public $queries = array();

    /**
     * @param string
     * @param array
     * @param array
     */
    public function startQuery($sql, array $params = NULL, array $types = NULL)
    {
        Debugger::timer('doctrine');

        $this->queries[] = array($sql, $params, 0);
    }

    public function stopQuery()
    {
        $keys = array_keys($this->queries);
        $key = end($keys);
        $this->queries[$key][2] = Debugger::timer('doctrine');
        $this->totalTime += $this->queries[$key][2];
    }

    public function getTab()
    {
        return '<span title="Doctrine 2">'
            . '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAEYSURBVBgZBcHPio5hGAfg6/2+R980k6wmJgsJ5U/ZOAqbSc2GnXOwUg7BESgLUeIQ1GSjLFnMwsKGGg1qxJRmPM97/1zXFAAAAEADdlfZzr26miup2svnelq7d2aYgt3rebl585wN6+K3I1/9fJe7O/uIePP2SypJkiRJ0vMhr55FLCA3zgIAOK9uQ4MS361ZOSX+OrTvkgINSjS/HIvhjxNNFGgQsbSmabohKDNoUGLohsls6BaiQIMSs2FYmnXdUsygQYmumy3Nhi6igwalDEOJEjPKP7CA2aFNK8Bkyy3fdNCg7r9/fW3jgpVJbDmy5+PB2IYp4MXFelQ7izPrhkPHB+P5/PjhD5gCgCenx+VR/dODEwD+A3T7nqbxwf1HAAAAAElFTkSuQmCC" />'
            . count($this->queries) . ' queries'
            . ($this->totalTime ? ' / ' . sprintf('%0.1f', $this->totalTime * 1000) . 'ms' : '')
            . '</span>';
    }

    /**
     * @param array
     * @return string
     */
    protected function processQuery(array $query)
    {
        $s = '';
        $h = 'htmlSpecialChars';
        list($sql, $params, $time) = $query;

        $s .= '<tr><td>' . sprintf('%0.3f', $time * 1000);
        $s .= '</td><td class="nette-Doctrine2Panel-sql">' . \Nette\Database\Helpers::dumpSql($sql);
        $s .= '</td><td>' . \Nette\Diagnostics\Helpers::clickableDump($params, TRUE) . '</tr>';

        return $s;
    }

    protected function renderStyles()
    {
        return '<style> #nette-debug td.nette-Doctrine2Panel-sql { background: white !important }
			#nette-debug .nette-Doctrine2Panel-source { color: #BBB !important }
			#nette-debug nette-Doctrine2Panel tr table { margin: 8px 0; max-height: 150px; overflow:auto } </style>';
    }

    public function renderException($e)
    {
        if (($e instanceof \PDOException || $e instanceof \Nella\Models\Exception) && count($this->queries)) {
            if (!$e instanceof \PDOException) {
                $e = $e->getPrevious();
                if (!is_object($e) || !$e instanceof \PDOException) {
                    return;
                }
            }

            $s = '<table><tr><th>Time&nbsp;ms</th><th>SQL</th><th>Params</th></tr>';
            $s .= $this->processQuery(end($this->queries));
            $s .= '</table>';
            return array(
                'tab' => 'SQL',
                'panel' => $this->renderStyles() . '<div class="nette-inner nette-Doctrine2Panel">' . $s . '</div>',
            );
        }
    }

    public function getPanel()
    {
        $s = '';
        foreach ($this->queries as $query) {
            $s .= $this->processQuery($query);
        }

        return empty($this->queries) ? '' :
            $this->renderStyles() .
                '<h1>Queries: ' . count($this->queries) . ($this->totalTime ? ', time: ' . sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : '') . '</h1>
			<div class="nette-inner nette-Doctrine2Panel">
			<table>
			<tr><th>Time&nbsp;ms</th><th>SQL</th><th>Params</th></tr>' . $s . '
			</table>
			</div>';
    }

    /**
     * @return Panel
     */
    public static function register()
    {
        $panel = new static;
        Debugger::$bar->addPanel($panel);
        Debugger::$blueScreen->addPanel(callback($panel, 'renderException'));
        return $panel;
    }
}
