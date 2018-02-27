<?php

/**
 * FInvoice Summation By User Dashboard Class.
 *
 * @copyright YetiForce Sp. z o.o
 * @license YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 * @author Radosław Skrzypczak <r.skrzypczak@yetiforce.com>
 */
class FInvoice_SummationByUser_Dashboard extends Vtiger_IndexAjax_View
{
	/**
	 * Process.
	 *
	 * @param \App\Request $request
	 */
	public function process(\App\Request $request)
	{
		$linkId = $request->getInteger('linkid');
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$userId = $currentUser->getId();
		$widget = Vtiger_Widget_Model::getInstance($linkId, $userId);
		if ($request->has('time')) {
			$time = $request->getDateRange('time');
		} else {
			$time = Settings_WidgetsManagement_Module_Model::getDefaultDate($widget);
			if ($time === false) {
				$time['start'] = date('Y-m-01');
				$time['end'] = date('Y-m-t');
			}
			// date parameters passed, convert them to YYYY-mm-dd
			$time['start'] = \App\Fields\Date::formatToDisplay($time['start']);
			$time['end'] = \App\Fields\Date::formatToDisplay($time['end']);
		}

		$viewer = $this->getViewer($request);
		$moduleName = $request->getModule();

		$param = \App\Json::decode($widget->get('data'));
		$data = $this->getWidgetData($moduleName, $param, $time);

		$viewer->assign('DTIME', $time);
		$viewer->assign('DATA', $data);
		$viewer->assign('WIDGET', $widget);
		$viewer->assign('PARAM', $param);
		$viewer->assign('MODULE_NAME', $moduleName);
		$viewer->assign('CURRENTUSER', $currentUser);
		if ($request->has('content')) {
			$viewer->view('dashboards/SummationByUserContents.tpl', $moduleName);
		} else {
			$viewer->view('dashboards/SummationByUser.tpl', $moduleName);
		}
	}

	/**
	 * Get widget data.
	 *
	 * @param string $moduleName
	 * @param array  $widgetParam
	 * @param string $time
	 *
	 * @return array
	 */
	public function getWidgetData($moduleName, $widgetParam, $time)
	{
		$s = new \yii\db\Expression('sum(sum_gross)');
		$queryGenerator = new \App\QueryGenerator($moduleName);
		$queryGenerator->setField('assigned_user_id');
		$queryGenerator->setCustomColumn(['s' => $s]);
		$queryGenerator->addCondition('saledate', $time['start'] . ',' . $time['end'], 'bw');
		$queryGenerator->setGroup('assigned_user_id');
		$query = $queryGenerator->createQuery();
		$query->orderBy(['s' => SORT_DESC]);
		$query->having(['>', $s, 0]);
		$dataReader = $query->createCommand()->query();
		$chartData = [
			'labels' => [],
			'datasets' => [
				[
					'data' => [],
					'backgroundColor' => [],
				//'borderColor' => [],
				//'tooltips' => [],
				//'links' => [], // links generated in proccess method
				],
			],
			'show_chart' => false
		];
		while ($row = $dataReader->read()) {
			$chartData['datasets'][0]['data'][] = (int) $row['s'];
			$chartData['labels'][] = \App\Fields\Owner::getLabel($row['assigned_user_id']);
			$chartData['datasets'][0]['backgroundColor'][] = \App\Fields\Owner::getColor($row['assigned_user_id']);
			$chartData['show_chart'] = true;
		}
		$dataReader->close();
		return $chartData;
	}
}
