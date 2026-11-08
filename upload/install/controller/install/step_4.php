<?php
class ControllerInstallStep4 extends Controller {
	public function index() {
		$this->load->language('install/step_4');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_step_4'] = $this->language->get('text_step_4');
		$data['text_catalog'] = $this->language->get('text_catalog');
		$data['text_admin'] = $this->language->get('text_admin');






		$data['text_project'] = $this->language->get('text_project');
		$data['text_project_description'] = $this->language->get('text_project_description');
		$data['text_project_visit'] = $this->language->get('text_project_visit');
		$data['text_documentation'] = $this->language->get('text_documentation');
		$data['text_documentation_description'] = $this->language->get('text_documentation_description');
		$data['text_documentation_visit'] = $this->language->get('text_documentation_visit');
		$data['text_issues'] = $this->language->get('text_issues');
		$data['text_issues_description'] = $this->language->get('text_issues_description');
		$data['text_issues_visit'] = $this->language->get('text_issues_visit');

		$data['error_warning'] = $this->language->get('error_warning');


		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('install/step_4', $data));
	}
}
