package de.idrinth.walled_secrets;

import java.io.IOException;
import java.security.KeyManagementException;
import java.security.KeyStoreException;
import java.security.NoSuchAlgorithmException;
import java.security.cert.CertificateException;
import javax.swing.ImageIcon;

public class SwingFactory
{
    private final LoginFrame login;
    private final ListFrame list;
    private final Request request;
    private final ImageIcon icon;

    public SwingFactory() throws CertificateException, KeyManagementException, KeyStoreException, IOException, NoSuchAlgorithmException {
        Config config = new Config();
        icon = new ImageIcon(getClass().getResource("/icons/logo64x64.png"));
        request = new Request(new TrustManager(), config);
        login = new LoginFrame("Walled Secrets", this, config);
        login.setIconImage(icon.getImage());
        list = new ListFrame("Walled Secrets", this, config, request);
        list.setIconImage(icon.getImage());
    }
    public void showLogin()
    {
        login.setVisible(true);
        list.setVisible(false);
    }
    public void showList()
    {
        login.setVisible(false);
        list.setVisible(true);
    }
    public void showNoteDetail(String id, String master)
    {
        login.setVisible(false);
        list.setVisible(true);
        NoteDetailsFrame noteDetail = new NoteDetailsFrame("Walled Secrets", this, request);
        noteDetail.display(true, id, master);
        noteDetail.setIconImage(icon.getImage());
    }
    public void showLoginDetail(String id, String master)
    {
        login.setVisible(false);
        list.setVisible(true);
        LoginDetailsFrame loginDetail = new LoginDetailsFrame("Walled Secrets", this, request);
        loginDetail.display(true, id, master);
        loginDetail.setIconImage(icon.getImage());
    }
}
