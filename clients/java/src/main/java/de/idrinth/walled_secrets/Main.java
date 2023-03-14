package de.idrinth.walled_secrets;

import java.io.IOException;
import java.security.KeyManagementException;
import java.security.KeyStoreException;
import java.security.NoSuchAlgorithmException;
import java.security.cert.CertificateException;

public class Main
{
    public static void main(String[] args) throws CertificateException, KeyManagementException, KeyStoreException, IOException, NoSuchAlgorithmException
    {
        (new SwingFactory()).showLogin();
    }
}
