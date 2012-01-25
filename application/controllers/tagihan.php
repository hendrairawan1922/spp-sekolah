<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Tagihan extends Alazka_Controller {
	public function __construct() {
		parent::__construct();
		// $this->output->enable_profiler(FALSE);
	}
	
	public function index($loadId = 0) {
		$this->load->model('Kelas_model');
		$this->load->model('Siswa_model');
		$this->load->model('Rate_model');
		$this->load->model('Invoice_model');
		
		// helper untuk melakukan repopulate checkbox, radio atau select
		$this->load->helper('mr_form');
		
		// Page title untuk HTML
		$this->data['page_title'] = 'Daftar Tagihan';
		
		// digunakan pada form action
		// membiarkan form action kosong bukanlah ide yang baik, dan itu
		// tergantung masing-masing browser implementasinya
		$this->data['action_url'] = site_url('tagihan/index');
		
		// untuk repopulate combo box filter
		$sess = new stdClass();
		$sess->id_siswa = $sess->nama_siswa = $sess->no_induk = $sess->kelas_jenjang = null;

		$this->data['list_tagihan'] = array();
		$this->data['reload'] = 0;
		
		// apakah ini proses form post
		if ($this->input->post('showbtn')) {
			$sess->id_siswa = $this->input->post('siswa_id');
			$sess->nama_siswa = $this->input->post('siswa');
			$sess->no_induk = $this->input->post('rep-siswa-induk');
			$sess->kelas_jenjang = $this->input->post('rep-siswa-kelas');

			$this->data['reload'] = 1;
			$this->show_invoices($sess);
		} else if ($loadId > 0) {
			$siswa = $this->Siswa_model->get_single_siswa(array('sis_siswa.id = '=>$loadId));

			$sess->id_siswa = $siswa->get_id();
			$sess->nama_siswa = $siswa->get_namalengkap();
			$sess->no_induk = $siswa->get_noinduk();
			$sess->kelas_jenjang = sprintf('%s (%s)', $siswa->kelas->get_kelas(), $siswa->kelas->get_jenjang());

			$this->data['reload'] = 1;
			$this->show_invoices($sess);
		}
		
		// URL untuk Ajax auto complete
		$this->data['ajax_siswa_url'] = site_url('tarif_khusus/get_ajax_siswa/10/');
		
		//$this->proses_bayar($sess);
		
		
		$this->data['sess'] = $sess;
		
		$this->load->view('site/header_view');
		$this->load->view('site/tagihan_view', $this->data);
		$this->load->view('site/footer_view');
	}
	
	private function show_invoices(&$sess) {
		$this->data['list_tagihan'] = array();
		
		try {
			$where = array(
					'ar_invoice.id_student' => (int)$sess->id_siswa,
			);
			$this->data['list_tagihan'] = $this->Invoice_model->get_all_open_invoice($where);
		} catch (InvoiceNotFoundException $e) {
			$this->set_flash_message('Tidak ada tagihan yang harus dibayar untuk siswa ini.');
			$this->data['list_tagihan'] = array();
		}
	}
	/**
	 * Method untuk menghapus tagihan.
	 *
	 * @author Rio Astamal <me@rioastamal.net>
	 *
	 * @param int $id - ID dari tagihan yang akan dihapus
	 * @param string $hash - MD5 hash dari $id, ini untuk mencegah kesalahan pengetikan pada URL
	 * @return void
	 */
	public function delete($id=0, $hash='') {
		$id = (int)$id;
		$this->load->model('Rate_model');
		$this->load->model('Invoice_model');
		
		// cari terlebih dulu tarif yang akan dihapus ada atau tidak
		try {
			$where = array('ar_invoice.id' => $id);
			$invoice = $this->Invoice_model->get_single_invoice($where);
			
			// ID ditemukan cocokkan hashnya
			if (md5($id) !== $hash) {
				throw new Exception ('Hash yang dimasukkan tidak cocok.');
			}
			
			if ($invoice->get_status() != 'open') throw new Exception('Hanya tagihan dengan status "open" yang bisa dihapus');
			// semua masih ok2 saja, jadi lakukan penghapusan sekarang
			$this->Invoice_model->delete($invoice);
			
			$this->set_flash_message('Data tagihan berhasil dihapus.');
			
			$this->index($invoice->get_id_student());
			
			return TRUE;
		} catch (InvoiceNotFoundException $e) {
			$this->set_flash_message('Maaf, tagihan yang hendak anda hapus tidak ditemukan.', 'error msg');
		} catch (Exception $e) {
			$this->set_flash_message($e->getMessage(), 'error msg');
		}
		
		$this->index();
	}

	/**
	 * Method untuk menampilkan link edit sesuai dengan ID dari tagihan. Method
	 * biasanya digunakan pada view.
	 *
	 * @author Rio Astamal <me@rioastamal.net>
	 *
	 * @param Rate $tarif - Instance dari object Custom_Rate
	 * @return string
	 */
	public function get_edit_link($model) {
		return site_url('tagihan/edit/' . $model->get_id());
	}
	
	/**
	 * Method untuk menampilkan link edit sesuai dengan ID dari tagihan. Method
	 * biasanya digunakan pada view.
	 *
	 * @author Rio Astamal <me@rioastamal.net>
	 *
	 * @param Rate $tarif - Instance dari object Custom_Rate
	 * @return string
	 */
	public function get_delete_link($model) {
		$token = md5($model->get_id());
		return site_url('tagihan/delete/' . $model->get_id() . '/' . $token);
	}
	
	
	/**
	 * Method untuk menambahkan javascript baru pada HEAD
	 *
	 * @author Rio Astamal <me@rioastamal>
	 *
	 * @return void
	 */
	public function add_more_javascript() {
		$script = '
		<script type="text/javascript" src="%s"></script>
		<script type="text/javascript" src="%s"></script>
		';
		printf($script, base_url() . 'js/json.suggest.js', base_url() . 'js/jquery.chained.min.js');
	}

	public function create($loadId=null, $invoiceEdited=null) {
		$this->load->model('Kelas_model');
		$this->load->model('Siswa_model');
		$this->load->model('Rate_model');

		$this->load->helper('mr_form');

		if (empty($invoiceEdited)) {
			$this->data['action'] = 'new';
			$this->data['page_title'] = 'Input Tagihan';
		} else {
			$this->data['action'] = 'edit';
			$this->data['page_title'] = 'Ubah Tagihan';
			$this->data['model'] = $invoiceEdited;
			$loadId = $invoiceEdited->get_id_student();

			if ($invoiceEdited->get_status() == 'paid')
				$this->set_flash_message('Anda mengubah tagihan yang sedang dicicil.');
			else if ($invoiceEdited->get_status() == 'closed') 
				$this->set_flash_message('Tidak bisa mengubah tagihan yang telah dilunasi. Silahkan batalkan dulu pembayaran.', 'error msg');
			
		}
		$this->data['sess'] = null;
		$this->data['action_url'] = site_url('tagihan/simpan');
		$this->data['info_url'] = site_url('tagihan/info');

		$this->data['ajax_siswa_url'] = site_url('tarif_khusus/get_ajax_siswa/10/');

		try {
			$this->data['list_tarif'] = $this->Rate_model->get_all_rate();
		} catch (Exception $e) {
			$this->data['list_tarif'] = array();
		}

		if (! empty($loadId)) {
			$this->data['sess'] = $this->Siswa_model->find_by_pk($loadId);
		}

		$this->load->view('site/header_view');
		$this->load->view('site/form_entri_tagihan_view', $this->data);
		$this->load->view('site/footer_view');
	}

	public function info() {
		$this->load->model('Kelas_model');
		$this->load->model('Siswa_model');
		$this->load->model('Rate_model');
		$this->load->model('Invoice_model');
		$this->load->model('TahunAkademik_model');

		$id_student = $this->input->post('id', false);
		$id_rate    = $this->input->post('rate', false);

		if (empty($id_student) || empty($id_rate)) $data = null;
		else {
			$tagihan = $this->Rate_model->find_by_pk($id_rate);

			$data = array();
			$data['tagihan'] = $tagihan->get_name();
			$data['jumlah']  = $tagihan->get_fare();

			switch ($tagihan->get_recurrence()) {
				case 'bulan': 
					$data['waktu']   = $this->TahunAkademik_model->berjalan->get_bulan('nama') . ' ' . $this->TahunAkademik_model->berjalan->get_tahun();
					break;
				case 'semester': 
					$data['waktu']   = $this->TahunAkademik_model->berjalan->get_semester('label') . ' ' . $this->TahunAkademik_model->berjalan->get_tahun();
					break;
				default: $data['waktu']  =  $this->TahunAkademik_model->berjalan->get_tahun();
			}
		}

		header('Content-type: application/json');
		echo json_encode($data);
	}

	public function simpan($validasi = null) {
		if ($this->input->server("REQUEST_METHOD") != 'POST') {
			redirect(site_url('tagihan'));
		} else if ($validasi == 1) {
			$errors = array();

			if ($this->input->post('action') == 'new')
				$required = explode(',', 'jumlah,tagihan,siswa_id,keterangan');
			else
				$required = explode(',', 'jumlah,keterangan');

			foreach ($required as $r) {
				$$r = trim($this->input->post($r));
				if (empty($$r)) array_push($errors, $r . ' belum diisi');
			}

			if (! is_numeric($jumlah)) array_push($errors, 'jumlah hanya bisa diisi angka');
			else if ($this->input->post('action') == 'new') {
				$this->load->model('Kelas_model');
				$this->load->model('Siswa_model');
				$this->load->model('Rate_model');
				$this->load->model('Invoice_model');
				$this->load->model('TahunAkademik_model');

				try {
				$siswa = $this->Siswa_model->find_by_pk($siswa_id);
				$rate  = $this->Rate_model->find_by_pk($tagihan);
				} 
				catch (SiswaNotFoundException $e) { array_push($errors, 'data siswa tidak ditemukan'); }
				catch (RateNotFoundException $e) { array_push($errors, 'data tagihan tidak ditemukan'); }

				if (empty($errors)) {
					$model = new Invoice;
					$model->rate = $rate;
					$model->siswa = $siswa;

					$code = $model->generate_code($this->TahunAkademik_model->berjalan);

					try {
						$invoice = $this->Invoice_model->get_single_invoice(array('ar_invoice.code'=>$code));
						if (!empty($invoice)) array_push($errors, $rate->get_name() . ' sudah pernah ditagih dengan nomor invoice "' . $invoice->get_code()  . '"');
					} catch (InvoiceNotFoundException $e) {
						//good to go
					}
				}
			}

			if (empty($errors)) echo 'SUCCESS';
			else {
				echo '<span> - ' . implode('</span><br/><span> - ', $errors) . '</span>';
			}
		} else {
			$this->load->model('Kelas_model');
			$this->load->model('Siswa_model');
			$this->load->model('Rate_model');
			$this->load->model('Invoice_model');
			$this->load->model('TahunAkademik_model');

			//get user data
			$model = new Invoice;

			try {
				$editId = $this->input->post('id');

				if ($editId > 0) {
					$model = $this->Invoice_model->find_by_pk($editId);
					$model->isNew = false;
					$model->siswa = $this->Siswa_model->find_by_pk($model->get_id_student());

				} else {
					$model->isNew = true;

					$model->set_id_rate($this->input->post('tagihan'));
					$model->set_id_student($this->input->post('siswa_id'));
					$model->rate = $this->Rate_model->find_by_pk($model->get_id_rate());
					$model->siswa = $this->Siswa_model->find_by_pk($model->get_id_student());
					$model->set_code($this->TahunAkademik_model->berjalan);
				}
				//info yg boleh diubah cuman ini
				$model->set_notes($this->input->post('catatan'));
				$model->set_amount($this->input->post('jumlah'));
				//$model->set_id_rate($this->input->post('tagihan'));
				$model->set_description($this->input->post('keterangan'));

				if ($this->save($model)) {
					$mesg = sprintf('Tagihan "%s" untuk siswa <strong>%s</strong> berhasil disimpan.', $model->get_description(), $model->siswa->get_namalengkap());
					$this->set_flash_message($mesg, 'information msg');
				}



			} catch (InvoiceNotFoundException $e) {
				$this->set_flash_message('Data tagihan tidak ditemukan', 'error msg');
			}

			//var_dump($model);
			//redirect(site_url('tagihan/index/' . $model->siswa->get_id()));
			$this->index($model->get_id_student());
		}
	}

	private function save($invoice) {
		$result = false;

		if ($invoice->isNew) {
			try {
				$this->Invoice_model->get_single_invoice(array('ar_invoice.code'=>$invoice->get_code()));
			} catch (InvoiceNotFoundException $e) {
				$result = $this->Invoice_model->insert($invoice);
			}
		} else $result = $this->Invoice_model->update($invoice, array('isNew', 'rate', 'siswa', 'id_rate', 'id_student', 'code', 'status', 'due_date', 'created', 'created_date', 'received_amount', 'last_installment'));

		return $result;
	}

	public function edit($loadId) {
		$this->load->model('Rate_model');
		$this->load->model('Invoice_model');

		try {
			$invoice = $this->Invoice_model->find_by_pk($loadId);
			$this->create($invoice->get_id_student(), $invoice);
		}	catch (InvoiceNotFoundException $e) {
			$this->set_flash_message('Data tagihan tidak ditemukan.', 'error msg');
			$this->data['list_tagihan'] = array();
			$this->create();
		}
	}
}