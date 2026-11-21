<?php 
include 'function/function_rupiah.php';
include 'koneksi.php';
include 'header.php';
 ?>
<?php show_flash(); ?>
 <div class="card shadow mb-4">
  <div class="card-body">
  	<form action="" method="get">
 	<table class="table">
 		<tr>
 			<td>NISN</td>
 			<td>:</td>
 			<td><input type="text" name="nisn" placeholder="Masukan Nisn Siswa" class="form-control"></td>
 			<td><button type="submit" class="btn btn-primary" name="cari">Search</button></td>
 		</tr>    	        	
    </table>
        </form>
</div>
</div>
<?php 
if (isset($_GET['nisn']) && $_GET['nisn'] != ''){
        $nisn = trim((string) $_GET['nisn']);
        $stmt = mysqli_prepare($conn, 'SELECT siswa.*, angkatan.*, jurusan.*, kelas.* FROM siswa
            JOIN angkatan ON siswa.id_angkatan = angkatan.id_angkatan
            JOIN jurusan ON siswa.id_jurusan = jurusan.id_jurusan
            JOIN kelas ON siswa.id_kelas = kelas.id_kelas
            WHERE siswa.nisn = ?');
        mysqli_stmt_bind_param($stmt, 's', $nisn);
        mysqli_stmt_execute($stmt);
        $exec = mysqli_stmt_get_result($stmt);

        // die();
        if(mysqli_num_rows($exec) !== 0) {
            $siswa = mysqli_fetch_assoc($exec);
            $id_siswa= $siswa['id_siswa'];
            $nisn = $siswa['nisn'];
                    ?>

                   <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Biodata Siswa</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                             
                                        <tr>
                                                <td>NISN</td>
                                                <td><?= $siswa['nisn'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Nama Siswa</td>
                                                <td><?= $siswa['nama'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Kelas</td>
                                                <td><?= $siswa['nama_kelas'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Tahun ajaran </td>
                                                <td><?= $siswa['nama_angkatan'] ?></td>
                                            </tr>
                             
                             
                                </table>
                            </div>
                        </div>
                    </div>
      
              <!-- Begin Page Content -->
           

                  
                    <!-- DataTales Example -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Data Pembayaran</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">

                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                     <thead>
                                        <tr>
                                            <td>NO</td>
                                            <th>Bulan</th>
                                            <th>Jatuh Tempo</th>
                                            <th>No Bayar</th>
                                            <th>Tanggal Bayar</th>
                                            <th>Jumlah</th>
                                            <th>Keterangan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    
                                    <tbody>
                                      <?php 
                                   $no=1;
                                    $query = "SELECT * from pembayaran WHERE id_siswa = '$id_siswa' order by jatuhtempo ASC ";
                                    $exec = mysqli_query($conn,$query);
                                    while($res = mysqli_fetch_assoc($exec)) { ?>
                                         <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= $res['bulan'] ?></td>
                                            <td><?= $res['jatuhtempo'] ?></td>
                                            <td><?= $res['nobayar'] ?></td>
                                            <td><?= $res['tglbayar'] ?></td>
                                            <td><?= format_rupiah($res['jumlah']) ?></td>
                                            <td><?= $res['ket'] ?></td>
                                            <td>
                                                <?php
                                            if ($res['nobayar'] == ''){
                                                        echo '<form method="post" action="proses_transaksi.php" class="d-inline">'
                                                            . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
                                                            . '<input type="hidden" name="act" value="bayar">'
                                                            . '<input type="hidden" name="id" value="' . (int) $res['idspp'] . '">'
                                                            . '<input type="hidden" name="nisn" value="' . e($nisn) . '">'
                                                            . '<button type="submit" class="btn btn-primary btn-sm" onclick="return confirm(\'Proses pembayaran bulan ini?\')">Bayar</button></form>';
                                            }else {
                                                        echo '<form method="post" action="proses_transaksi.php" class="d-inline mr-1">'
                                                            . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
                                                            . '<input type="hidden" name="act" value="batal">'
                                                            . '<input type="hidden" name="id" value="' . (int) $res['idspp'] . '">'
                                                            . '<input type="hidden" name="nisn" value="' . e($nisn) . '">'
                                                            . '<button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Batalkan pembayaran ini?\')">Batal</button></form>';
                                                        echo '<a class="btn btn-success btn-sm" href="cetak_slip_pembayaran.php?idspp=' . (int) $res['idspp'] . '" target="_blank">Cetak</a>';
                                                
                                            }
                                                 ?>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                   </tbody>
                               
                                </table>

                            </div>
                        </div>
                 <?php 
         }else {
                ?>
                 <div class="card shadow mb-4">
              <div class="card-body">
               <h5>Data Tidak Tersedia</h5>
            </div>
            </div>
               <?php } ?>

       <?php } ?>
          </div>
 <?php include 'footer.php'; ?>